<?php
/**
 * Plugin Name: Fines Manager
 * Plugin URI: https://github.com/erwansetyobudi/fines_manager
 * Description: To view, edit and delete fines
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
        $finesDate = trim($_POST['finesDate']);
        $memberID = trim($_POST['memberID']);
        $debet = (int)trim($_POST['debet']);
        $credit = (int)trim($_POST['credit']);
        $kodeEksemplar = isset($_POST['kodeEksemplar']) ? trim($_POST['kodeEksemplar']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        // If in add mode and kodeEksemplar is provided, use it in description
        if (empty($description) && !empty($kodeEksemplar)) {
            $description = 'Overdue fines for item ' . $kodeEksemplar;
        }
        
        if (empty($finesDate) OR empty($memberID)) {
            toastr('Tanggal dan ID Anggota tidak boleh kosong')->error();
            exit();
        }
        
        // Prepare data array
        $data = array();
        $data['fines_date'] = $dbs->escape_string($finesDate);
        $data['member_id'] = $dbs->escape_string($memberID);
        $data['debet'] = $debet;
        $data['credit'] = $credit;
        $data['description'] = $dbs->escape_string($description);
        $data['last_update'] = date('Y-m-d H:i:s');
        
        // Only set input_date for new records
        if (!isset($_POST['updateRecordID'])) {
            $data['input_date'] = date('Y-m-d H:i:s');
        }

        // Create SQL operation object
        $sql_op = new simbio_dbop($dbs);
        
        $base_url = $_SERVER['PHP_SELF'];
        $query_str = isset($_POST['lastQueryStr']) && !empty($_POST['lastQueryStr']) ? $_POST['lastQueryStr'] : 'plugin=fines_manager';

        if (isset($_POST['updateRecordID'])) {
            // UPDATE MODE
            $updateRecordID = (int)$dbs->escape_string(trim($_POST['updateRecordID']));
            unset($data['input_date']); // Remove input_date for update
            
            $update = $sql_op->update('fines', $data, "fines_id='$updateRecordID'");
            if ($update) {
                toastr('Data Denda berhasil diperbarui')->success();
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', $_SESSION['realname'].' memperbarui data denda ID '.$updateRecordID);
                echo '<script type="text/javascript">parent.$("#mainContent").simbioAJAX("' . $base_url . '?' . $query_str . '");</script>';
            } else {
                toastr('Data Denda GAGAL diperbarui. Silakan hubungi Administrator')->error();
            }
        } else {
            // INSERT MODE
            $insert = $sql_op->insert('fines', $data);
            if ($insert) {
                toastr('Data Denda baru berhasil disimpan')->success();
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', $_SESSION['realname'].' menambahkan data denda baru');
                echo '<script type="text/javascript">parent.$("#mainContent").simbioAJAX("' . $base_url . '?' . $query_str . '");</script>';
            } else {
                toastr('Data Denda GAGAL disimpan. Silakan hubungi Administrator')->error();
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
            if (!$sql_op->delete('fines', "fines_id='$itemID'")) {
                $error_num++;
            } else {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'membership', $_SESSION['realname'].' menghapus data denda ID '.$itemID, 'Delete', 'OK');
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
$table_check = $dbs->query("SHOW TABLES LIKE 'fines'");
if ($table_check->num_rows == 0) {
    // Create table if not exists
    $create_table = "
    CREATE TABLE IF NOT EXISTS `fines` (
        `fines_id` int(11) NOT NULL AUTO_INCREMENT,
        `fines_date` date NOT NULL,
        `member_id` varchar(20) NOT NULL,
        `debet` int(11) DEFAULT '0',
        `credit` int(11) DEFAULT '0',
        `description` text,
        `input_date` datetime DEFAULT NULL,
        `last_update` datetime DEFAULT NULL,
        PRIMARY KEY (`fines_id`),
        KEY `member_id` (`member_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($dbs->query($create_table)) {
        echo '<div class="infoBox">Table "fines" has been created successfully.</div>';
    } else {
        die('<div class="errorBox">Failed to create table "fines". Please create it manually.</div>');
    }
}

/* search form */
?>
<div class="menuBox">
<div class="menuBoxInner memberIcon">
    <div class="per_title">
        <h2>Data Denda Pemustaka</h2>
    </div>
    <div class="sub_section">
        <div class="btn-group">
            <a href="<?= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] ?>" class="btn btn-default">Daftar Denda</a>
            <a href="<?= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] ?>?&action=detail" class="btn btn-default">Tambah Denda Baru</a>
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
        $query = $dbs->query("SELECT * FROM fines WHERE fines_id='$itemID'");
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
    if ($rec_d && isset($rec_d['fines_id'])) {
        $form->edit_mode = true;
        // record ID for delete process
        $form->record_id = $itemID;
        // form record title
        $form->record_title = 'Denda ID '.$itemID;
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="s-btn btn btn-primary"';
        // Add hidden field for update
        $form->addHidden('updateRecordID', $itemID);
    }

    /* Form Element(s) */
    // fines date
    $default_date = isset($rec_d['fines_date']) ? $rec_d['fines_date'] : date('Y-m-d');
    $form->addDateField('finesDate', 'Tanggal Denda*', $default_date, 'class="form-control"');

    // member ID
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

    // debet
    $debet_value = isset($rec_d['debet']) ? $rec_d['debet'] : '0';
    $form->addTextField('text', 'debet', 'Debet', $debet_value, 'class="form-control" style="width: 20%;"');
    
    // credit
    $credit_value = isset($rec_d['credit']) ? $rec_d['credit'] : '0';
    $form->addTextField('text', 'credit', 'Kredit', $credit_value, 'class="form-control" style="width: 20%;"');
    
    if (!$form->edit_mode) {
        // TAMBAH denda baru: tampilkan Kode Eksemplar
        $form->addTextField('text', 'kodeEksemplar', 'Kode Eksemplar*', '', 'class="form-control" style="width:50%;"');
        // Add description as hidden or textarea
        $form->addTextField('textarea', 'description', 'Keterangan', '', 'class="form-control" style="width: 100%;" rows="3"');
    } else {
        // EDIT denda: tampilkan Keterangan
        $description_value = isset($rec_d['description']) ? $rec_d['description'] : '';
        $form->addTextField('textarea', 'description', 'Keterangan', $description_value, 'class="form-control" style="width: 100%;" rows="3"');
    }

    // edit mode message
    if ($form->edit_mode) {
        echo '<div class="infoBox">';
        echo 'Anda sedang mengedit data denda: <strong>ID '.$itemID.'</strong>';
        if (isset($rec_d['last_update'])) {
            echo '<div>Terakhir diperbarui: '.$rec_d['last_update'].'</div>';
        }
        echo '</div>';
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* FINES LIST */
    // table spec
    $table_spec = 'fines AS f 
        LEFT JOIN member AS m ON f.member_id=m.member_id';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('f.fines_id',
            'f.fines_id AS \'ID Denda\'',
            'f.fines_date AS \'Tanggal Denda\'',
            'f.member_id AS \'ID Anggota\'',
            'm.member_name AS \'Nama Anggota\'',
            'f.debet AS \'Debet\'',
            'f.credit AS \'Kredit\'',
            'f.description AS \'Keterangan\'',
            'f.last_update AS \'Terakhir Diperbarui\'');
    } else {
        $datagrid->setSQLColumn('f.fines_date AS \'Tanggal Denda\'',
            'f.member_id AS \'ID Anggota\'',
            'm.member_name AS \'Nama Anggota\'',
            'f.debet AS \'Debet\'',
            'f.credit AS \'Kredit\'',
            'f.description AS \'Keterangan\'',
            'f.last_update AS \'Terakhir Diperbarui\'');
    }
    $datagrid->setSQLorder('f.fines_date DESC, f.last_update DESC');

    // is there any search
    $criteria = 'f.fines_id IS NOT NULL';
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $criteria .= " AND (m.member_name LIKE '%$keywords%' OR f.member_id LIKE '%$keywords%' OR f.description LIKE '%$keywords%')";
    }
    $datagrid->setSQLCriteria($criteria);

    // set table and table header attributes
    $datagrid->icon_edit = SWB.'admin/'.$sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
    $datagrid->table_name = 'finesList';
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
