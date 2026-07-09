<?php
/**
 * Plugin Name: Loan Manager
 * Plugin URI: https://github.com/erwansetyobudi/loan_manager
 * Description: To view, edit and delete loan data
 * Version: 0.0.1
 * Author: Erwan Setyo Budi
 * Author URI: https://github.com/erwansetyobudi
 */

use SLiMS\Plugins;
defined('INDEX_AUTH') OR die('Direct access not allowed!');

// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');
// start the session
require SB . 'admin/default/session.inc.php';
require SIMBIO . 'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO . 'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO . 'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO . 'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO . 'simbio_DB/simbio_dbop.inc.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

if (!$can_read) {
    die('<div class="errorBox">Anda tidak memiliki hak akses untuk melihat bagian ini</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData'])) {
    try {
        // check form validity
        $itemCode = trim($_POST['itemCode']);
        $memberID = trim($_POST['memberID']);
        $loanDate = trim($_POST['loanDate']);
        $dueDate = trim($_POST['dueDate']);
        $renewed = isset($_POST['renewed']) ? (int)trim($_POST['renewed']) : 0;
        $loanRulesId = isset($_POST['loanRulesId']) ? (int)trim($_POST['loanRulesId']) : 0;
        $actual = isset($_POST['actual']) && !empty($_POST['actual']) ? trim($_POST['actual']) : null;
        $isLent = isset($_POST['isLent']) ? (int)trim($_POST['isLent']) : 0;
        $isReturn = isset($_POST['isReturn']) ? (int)trim($_POST['isReturn']) : 0;
        $returnDate = isset($_POST['returnDate']) && !empty($_POST['returnDate']) ? trim($_POST['returnDate']) : null;
        $uid = isset($_POST['uid']) ? (int)trim($_POST['uid']) : $_SESSION['uid'];
        
        // Validate required fields
        if (empty($itemCode) OR empty($memberID) OR empty($loanDate) OR empty($dueDate)) {
            toastr('Kode Item, ID Anggota, Tanggal Pinjam, dan Tanggal Jatuh Tempo tidak boleh kosong')->error();
            exit();
        }
        
        // Prepare data array
        $data = array();
        $data['item_code'] = $dbs->escape_string($itemCode);
        $data['member_id'] = $dbs->escape_string($memberID);
        $data['loan_date'] = $dbs->escape_string($loanDate);
        $data['due_date'] = $dbs->escape_string($dueDate);
        $data['renewed'] = $renewed;
        $data['loan_rules_id'] = $loanRulesId;
        $data['actual'] = $actual ? $dbs->escape_string($actual) : null;
        $data['is_lent'] = $isLent;
        $data['is_return'] = $isReturn;
        $data['return_date'] = $returnDate ? $dbs->escape_string($returnDate) : null;
        $data['uid'] = $uid;
        $data['last_update'] = date('Y-m-d H:i:s');
        
        // Only set input_date for new records
        if (!isset($_POST['updateRecordID'])) {
            $data['input_date'] = date('Y-m-d H:i:s');
        }

        // Create SQL operation object
        $sql_op = new simbio_dbop($dbs);
        
        $base_url = $_SERVER['PHP_SELF'];
        $query_str = isset($_POST['lastQueryStr']) && !empty($_POST['lastQueryStr']) ? $_POST['lastQueryStr'] : 'plugin=loan_manager';

        if (isset($_POST['updateRecordID'])) {
            // UPDATE MODE
            $updateRecordID = (int)$dbs->escape_string(trim($_POST['updateRecordID']));
            unset($data['input_date']); // Remove input_date for update
            
            $update = $sql_op->update('loan', $data, "loan_id='$updateRecordID'");
            if ($update) {
                toastr('Data Peminjaman berhasil diperbarui')->success();
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'circulation', $_SESSION['realname'].' memperbarui data peminjaman ID '.$updateRecordID);
                echo '<script type="text/javascript">parent.$("#mainContent").simbioAJAX("' . $base_url . '?' . $query_str . '");</script>';
            } else {
                toastr('Data Peminjaman GAGAL diperbarui. Silakan hubungi Administrator')->error();
            }
        } else {
            // INSERT MODE
            $insert = $sql_op->insert('loan', $data);
            if ($insert) {
                toastr('Data Peminjaman baru berhasil disimpan')->success();
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'circulation', $_SESSION['realname'].' menambahkan data peminjaman baru');
                echo '<script type="text/javascript">parent.$("#mainContent").simbioAJAX("' . $base_url . '?' . $query_str . '");</script>';
            } else {
                toastr('Data Peminjaman GAGAL disimpan. Silakan hubungi Administrator')->error();
            }
        }
        exit();
    } catch (Exception $e) {
        toastr('Error: ' . $e->getMessage())->error();
        exit();
    }
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    /* DATA DELETION PROCESS */
    try {
        $sql_op = new simbio_dbop($dbs);
        $error_num = 0;
        if (!is_array($_POST['itemID'])) {
            // make an array
            $_POST['itemID'] = array((int)$dbs->escape_string(trim($_POST['itemID'])));
        }
        // loop array
        foreach ($_POST['itemID'] as $itemID) {
            $itemID = (int)$dbs->escape_string(trim($itemID));
            if (!$sql_op->delete('loan', "loan_id='$itemID'")) {
                $error_num++;
            } else {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'circulation', $_SESSION['realname'].' menghapus data peminjaman ID '.$itemID, 'Delete', 'OK');
            }
        }

        // error alerting
        if ($error_num == 0) {
            toastr('Semua Data berhasil dihapus')->success();
            echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
        } else {
            toastr('Beberapa Data tidak berhasil dihapus!')->error();
            echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
        }
        exit();
    } catch (Exception $e) {
        toastr('Error: ' . $e->getMessage())->error();
        exit();
    }
}
/* RECORD OPERATION END */

// Check if table exists
$table_check = $dbs->query("SHOW TABLES LIKE 'loan'");
if ($table_check->num_rows == 0) {
    // Create table if not exists
    $create_table = "
    CREATE TABLE IF NOT EXISTS `loan` (
        `loan_id` int(11) NOT NULL AUTO_INCREMENT,
        `item_code` varchar(20) DEFAULT NULL,
        `member_id` varchar(20) DEFAULT NULL,
        `loan_date` date NOT NULL,
        `due_date` date NOT NULL,
        `renewed` int(11) DEFAULT '0',
        `loan_rules_id` int(11) DEFAULT '0',
        `actual` date DEFAULT NULL,
        `is_lent` int(11) DEFAULT '0',
        `is_return` int(11) DEFAULT '0',
        `return_date` date DEFAULT NULL,
        `input_date` datetime DEFAULT NULL,
        `last_update` datetime DEFAULT NULL,
        `uid` int(11) DEFAULT NULL,
        PRIMARY KEY (`loan_id`),
        KEY `item_code` (`item_code`),
        KEY `member_id` (`member_id`),
        KEY `loan_rules_id` (`loan_rules_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($dbs->query($create_table)) {
        echo '<div class="infoBox">Table "loan" has been created successfully.</div>';
    } else {
        die('<div class="errorBox">Failed to create table "loan". Please create it manually.</div>');
    }
}

/* search form */
?>
<div class="menuBox">
<div class="menuBoxInner circulationIcon">
    <div class="per_title">
        <h2>Data Peminjaman</h2>
    </div>
    <div class="sub_section">
        <div class="btn-group">
            <a href="<?= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] ?>" class="btn btn-default">Daftar Peminjaman</a>
            <a href="<?= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] ?>?&action=detail" class="btn btn-default">Tambah Peminjaman Baru</a>
        </div>
        <form name="search" action="<?= $_SERVER['PHP_SELF'] ?>" id="search" method="get" class="form-inline"><?php echo __('Search'); ?>
            <input type="hidden" name="id" value="<?= isset($_GET['id']) ? $_GET['id'] : '' ?>"/>
            <input type="hidden" name="mod" value="<?= isset($_GET['mod']) ? $_GET['mod'] : '' ?>"/>
            <input type="text" name="keywords" class="form-control col-md-3" value="<?= isset($_GET['keywords']) ? htmlspecialchars($_GET['keywords']) : '' ?>" />
            <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="s-btn btn btn-default" />
        </form>
    </div>
</div>
</div>
<?php
/* search form end */
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {

    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">Anda tidak memiliki hak akses untuk melihat bagian ini</div>');
    }
    /* RECORD FORM */
    $itemID = (int)$dbs->escape_string(trim(isset($_POST['itemID'])?$_POST['itemID']:(isset($_GET['itemID'])?$_GET['itemID']:'0')));
    $rec_d = array();
    if ($itemID > 0) {
        $query = $dbs->query("SELECT * FROM loan WHERE loan_id='$itemID'");
        if ($query) {
            $rec_d = $query->fetch_assoc();
        }
    }

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    // Simpan ulang query string agar bisa digunakan saat redirect
    $form->addHidden('lastQueryStr', $_SERVER['QUERY_STRING']);

    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="s-btn btn btn-default"';

    // form table attributes
    $form->table_attr = 'id="dataList" class="s-table table"';
    $form->table_header_attr = 'class="alterCell font-weight-bold"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_d && isset($rec_d['loan_id'])) {
        $form->edit_mode = true;
        // record ID for delete process
        $form->record_id = $itemID;
        // form record title
        $form->record_title = 'Peminjaman ID '.$itemID;
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="s-btn btn btn-primary"';
        // Add hidden field for update
        $form->addHidden('updateRecordID', $itemID);
    }

    /* Form Element(s) */
    // Item Code
    $item_code_value = isset($rec_d['item_code']) ? $rec_d['item_code'] : '';
    $form->addTextField('text', 'itemCode', 'Kode Item*', $item_code_value, 'class="form-control" style="width:50%;" ' . ($form->edit_mode ? 'readonly' : ''));

    // Judul Item (ambil dari DB berdasarkan item_code saat edit)
    $judul_item = '';
    if (!empty($item_code_value)) {
        $getItem = $dbs->query("SELECT title FROM biblio WHERE biblio_id = (SELECT biblio_id FROM item WHERE item_code='" . $dbs->escape_string($item_code_value) . "')");
        if ($getItem && $getItem->num_rows > 0) {
            $row = $getItem->fetch_row();
            $judul_item = $row[0];
        }
    }
    $form->addTextField('text', 'judulItem', 'Judul Item', $judul_item, 'class="form-control" style="width:60%;" readonly');

    // Member ID
    $member_id_value = isset($rec_d['member_id']) ? $rec_d['member_id'] : '';
    $form->addTextField('text', 'memberID', 'ID Anggota*', $member_id_value, 'class="form-control" style="width:40%;" ' . ($form->edit_mode ? 'readonly' : ''));

    // Nama Anggota (ambil dari DB berdasarkan ID saat edit)
    $nama_anggota = '';
    if (!empty($member_id_value)) {
        $getname = $dbs->query("SELECT member_name FROM member WHERE member_id='" . $dbs->escape_string($member_id_value) . "'");
        if ($getname && $getname->num_rows > 0) {
            $row = $getname->fetch_row();
            $nama_anggota = $row[0];
        }
    }
    $form->addTextField('text', 'namaAnggota', 'Nama Anggota', $nama_anggota, 'class="form-control" style="width:60%;" readonly');

    // Loan Date
    $loan_date_value = isset($rec_d['loan_date']) ? $rec_d['loan_date'] : date('Y-m-d');
    $form->addDateField('loanDate', 'Tanggal Pinjam*', $loan_date_value, 'class="form-control"');

    // Due Date
    $due_date_value = isset($rec_d['due_date']) ? $rec_d['due_date'] : date('Y-m-d', strtotime('+7 days'));
    $form->addDateField('dueDate', 'Tanggal Jatuh Tempo*', $due_date_value, 'class="form-control"');

    // Renewed
    $renewed_value = isset($rec_d['renewed']) ? $rec_d['renewed'] : 0;
    $form->addTextField('text', 'renewed', 'Jumlah Perpanjangan', $renewed_value, 'class="form-control" style="width:20%;"');

    // Loan Rules ID
    $loan_rules_value = isset($rec_d['loan_rules_id']) ? $rec_d['loan_rules_id'] : 0;
    $form->addTextField('text', 'loanRulesId', 'ID Aturan Peminjaman', $loan_rules_value, 'class="form-control" style="width:20%;"');

    // Actual Return Date
    $actual_value = isset($rec_d['actual']) ? $rec_d['actual'] : '';
    $form->addDateField('actual', 'Tanggal Kembali Aktual', $actual_value, 'class="form-control"');

    // Is Lent - Using addAnything with custom HTML select
    $is_lent_value = isset($rec_d['is_lent']) ? $rec_d['is_lent'] : 0;
    $is_lent_options = array('0' => 'Belum Dipinjam', '1' => 'Sedang Dipinjam');
    $select_html = '<select name="isLent" class="form-control">';
    foreach ($is_lent_options as $value => $label) {
        $selected = ($value == $is_lent_value) ? ' selected="selected"' : '';
        $select_html .= '<option value="'.$value.'"'.$selected.'>'.$label.'</option>';
    }
    $select_html .= '</select>';
    $form->addAnything('Status Pinjam', $select_html);

    // Is Return - Using addAnything with custom HTML select
    $is_return_value = isset($rec_d['is_return']) ? $rec_d['is_return'] : 0;
    $is_return_options = array('0' => 'Belum Kembali', '1' => 'Sudah Kembali');
    $select_html2 = '<select name="isReturn" class="form-control">';
    foreach ($is_return_options as $value => $label) {
        $selected = ($value == $is_return_value) ? ' selected="selected"' : '';
        $select_html2 .= '<option value="'.$value.'"'.$selected.'>'.$label.'</option>';
    }
    $select_html2 .= '</select>';
    $form->addAnything('Status Kembali', $select_html2);

    // Return Date
    $return_date_value = isset($rec_d['return_date']) ? $rec_d['return_date'] : '';
    $form->addDateField('returnDate', 'Tanggal Kembali', $return_date_value, 'class="form-control"');

    // UID (Staff ID)
    $uid_value = isset($rec_d['uid']) ? $rec_d['uid'] : $_SESSION['uid'];
    $form->addTextField('text', 'uid', 'ID Staff', $uid_value, 'class="form-control" style="width:20%;" readonly');

    // edit mode message
    if ($form->edit_mode) {
        echo '<div class="infoBox">';
        echo 'Anda sedang mengedit data peminjaman: <strong>ID '.$itemID.'</strong>';
        if (isset($rec_d['last_update'])) {
            echo '<div>Terakhir diperbarui: '.$rec_d['last_update'].'</div>';
        }
        echo '</div>';
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* LOAN LIST */
    // table spec
    $table_spec = 'loan AS l 
        LEFT JOIN member AS m ON l.member_id=m.member_id
        LEFT JOIN item AS i ON l.item_code=i.item_code
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('l.loan_id',
            'l.loan_id AS \'ID Pinjam\'',
            'l.item_code AS \'Kode Item\'',
            'b.title AS \'Judul\'',
            'l.member_id AS \'ID Anggota\'',
            'm.member_name AS \'Nama Anggota\'',
            'l.loan_date AS \'Tgl Pinjam\'',
            'l.due_date AS \'Jatuh Tempo\'',
            'l.renewed AS \'Perpanjangan\'',
            'CASE WHEN l.is_lent = 1 THEN \'Dipinjam\' ELSE \'Tersedia\' END AS \'Status Pinjam\'',
            'CASE WHEN l.is_return = 1 THEN \'Kembali\' ELSE \'Belum Kembali\' END AS \'Status Kembali\'',
            'l.return_date AS \'Tgl Kembali\'',
            'l.last_update AS \'Terakhir Diperbarui\'');
    } else {
        $datagrid->setSQLColumn('l.item_code AS \'Kode Item\'',
            'b.title AS \'Judul\'',
            'l.member_id AS \'ID Anggota\'',
            'm.member_name AS \'Nama Anggota\'',
            'l.loan_date AS \'Tgl Pinjam\'',
            'l.due_date AS \'Jatuh Tempo\'',
            'l.renewed AS \'Perpanjangan\'',
            'CASE WHEN l.is_lent = 1 THEN \'Dipinjam\' ELSE \'Tersedia\' END AS \'Status Pinjam\'',
            'CASE WHEN l.is_return = 1 THEN \'Kembali\' ELSE \'Belum Kembali\' END AS \'Status Kembali\'',
            'l.return_date AS \'Tgl Kembali\'',
            'l.last_update AS \'Terakhir Diperbarui\'');
    }
    $datagrid->setSQLorder('l.loan_date DESC, l.last_update DESC');

    // is there any search
    $criteria = 'l.loan_id IS NOT NULL';
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $criteria .= " AND (m.member_name LIKE '%$keywords%' OR l.member_id LIKE '%$keywords%' OR l.item_code LIKE '%$keywords%' OR b.title LIKE '%$keywords%')";
    }
    $datagrid->setSQLCriteria($criteria);

    // set table and table header attributes
    $datagrid->icon_edit = SWB.'admin/'.$sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
    $datagrid->table_name = 'loanList';
    $datagrid->table_attr = 'id="dataList" class="s-table table"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'] . '?' .$_SERVER['QUERY_STRING'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        echo '<div class="infoBox">';
        echo 'Ditemukan '.$datagrid->num_rows.' data dengan kata kunci: "'.htmlspecialchars($_GET['keywords']).'"';
        echo '</div>';
    }

    echo $datagrid_result;
}
/* main content end */
?>