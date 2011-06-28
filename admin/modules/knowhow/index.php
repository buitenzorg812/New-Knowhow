<?php
/**
 * Copyright (C) 2007,2008,2009,2010  Arie Nugraha (dicarve@yahoo.com)
 * modified by wynerst@gmail.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* ABNR KnowHow Management section */

// key to authenticate
define('INDEX_AUTH', '1');

if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
}

require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';
require MODULES_BASE_DIR.'system/biblio_indexer.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');
$can_write = utility::havePrivilege('bibliography', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$in_pop_up = false;
// check if we are inside pop-up window
if (isset($_GET['inPopUp'])) {
    $in_pop_up = true;
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
    $title = trim(strip_tags($_POST['title']));
    // check form validity
    if (empty($title)) {
        utility::jsAlert(__('Title can not be empty'));
        exit();
    } else {
        // include custom fields file
        if (file_exists(MODULES_BASE_DIR.'knowhow/custom_fields.inc.php')) {
            include MODULES_BASE_DIR.'knowhow/custom_fields.inc.php';
        }

        // create biblio_indexer class instance
        $indexer = new biblio_indexer($dbs);

        $data['title'] = $dbs->escape_string($title);
        $data['number'] = trim($dbs->escape_string(strip_tags($_POST['number'])));
//        $data['publisher'] = trim($dbs->escape_string(strip_tags($_POST['publisher'])));
        $data['create_date'] = trim($dbs->escape_string(strip_tags($_POST['create_date'])));
        $data['main_text'] = trim($dbs->escape_string(strip_tags($_POST['main_text'])));
        $data['en_text'] = trim($dbs->escape_string(strip_tags($_POST['en_text'])));
        $data['notes'] = trim($dbs->escape_string(strip_tags($_POST['notes'], '<br><p><div><span><i><em><strong><b><code>s')));
        $data['tag'] = trim($dbs->escape_string(strip_tags($_POST['notes'])));
        $data['language_id'] = trim($dbs->escape_string(strip_tags($_POST['languageID'])));
        $data['kh_type'] = $_POST['kh_type'];
        $data['input_date'] = date('Y-m-d H:i:s');
        $data['last_update'] = date('Y-m-d H:i:s');

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
            // update data
            $update = $sql_op->update('knowhow', $data, 'biblio_id='.$updateRecordID);
            // send an alert
            if ($update) {
            	if ($sysconf['bibliography_update_notification']) {
                    utility::jsAlert(__('Know How Reporistory Data Successfully Updated'));
			    }
                // auto insert catalog to UCS if enabled
                if ($sysconf['ucs']['enable']) {
                    echo '<script type="text/javascript">parent.ucsUpload(\''.MODULES_WEB_ROOT_DIR.'knowhow/ucs_upload.php\', \'itemID[]='.$updateRecordID.'\', false);</script>';
                }
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'knowhow', $_SESSION['realname'].' update KH bibliographic data ('.$data['title'].') with biblio_id ('.$_POST['itemID'].')');
                // close window OR redirect main page
                if ($in_pop_up) {
                    $itemCollID = (integer)$_POST['itemCollID'];
                    echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url, {method: \'post\', addData: \''.( $itemCollID?'itemID='.$itemCollID.'&detail=true':'' ).'\'});</script>';
                    echo '<script type="text/javascript">top.closeHTMLpop();</script>';
                } else {
                    echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[1].url);</script>';
                }
                // update index
                // delete from index first
                $sql_op->delete('search_knowhow', "biblio_id=$updateRecordID");
                $indexer->makeIndex($updateRecordID);
            } else { utility::jsAlert(__('KH Bibliography Data FAILED to Updated. Please Contact System Administrator')."\n".$sql_op->error); }
            exit();
        } else {
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('knowhow', $data);
            if ($insert) {
                // get auto id of this record
                $last_biblio_id = $sql_op->insert_id;
                // add authors
				unset($author);
                if ($_SESSION['biblioAuthor']) {
                    foreach ($_SESSION['biblioAuthor'] as $author) {
                        $sql_op->insert('knowhow_author', array('biblio_id' => $last_biblio_id, 'author_id' => $author[0], 'level' => $author[1]));
                    }
                }
                // add docs_relation
				unset($relation);
                if ($_SESSION['kh_relation']) {
                    foreach ($_SESSION['kh_relation'] as $relation) {
                        $sql_op->insert('knowhow_relation', array('biblio_id' => $last_biblio_id, 'relation_id' => $relation[0], 'level' => $relation[1]));
                    }
                }
                // add topics
				unset($topic);
                if ($_SESSION['biblioTopic']) {
                    foreach ($_SESSION['biblioTopic'] as $topic) {
                        $sql_op->insert('knowhow_topic', array('biblio_id' => $last_biblio_id, 'topic_id' => $topic[0], 'level' => $topic[1]));
                    }
                }
                // add attachment
				unset($attachment);
                if ($_SESSION['biblioAttach']) {
                    foreach ($_SESSION['biblioAttach'] as $attachment) {
                        $sql_op->insert('knowhow_attachment', array('biblio_id' => $last_biblio_id, 'file_id' => $attachment['file_id'], 'access_type' => $attachment['access_type']));
                    }
                }
                // add tag
				unset($tag);
                if ($_SESSION['biblioTag']) {
                    foreach ($_SESSION['biblioTag'] as $tag) {
                        $sql_op->insert('knowhow_tag', array('biblio_id' => $last_biblio_id, 'topic_id' => $tag[0], 'level' => $tag[1]));
                    }
                }

                utility::jsAlert(__('New KH Bibliography Data Successfully Saved'));
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'knowhow', $_SESSION['realname'].' insert KH bibliographic data ('.$data['title'].') with biblio_id ('.$last_biblio_id.')');
                // clear related sessions
                $_SESSION['biblioAuthor'] = array();
                $_SESSION['biblioTopic'] = array();
                $_SESSION['biblioAttach'] = array();
                $_SESSION['biblioTag'] = array();
                $_SESSION['kh_relation'] = array();
                // update index
                $indexer->makeIndex($last_biblio_id);
                // auto insert catalog to UCS if enabled
                if ($sysconf['ucs']['enable'] && $sysconf['ucs']['auto_insert']) {
                    echo '<script type="text/javascript">parent.ucsUpload(\''.MODULES_WEB_ROOT_DIR.'knowhow/ucs_upload.php\', \'itemID[]='.$last_biblio_id.'\');</script>';
                }
                echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.MODULES_WEB_ROOT_DIR.'knowhow/index.php\', {method: \'post\', addData: \'itemID='.$last_biblio_id.'&detail=true\'});</script>';
            } else { utility::jsAlert(__('KH Bibliography Data FAILED to Save. Please Contact System Administrator')."\n".$sql_op->error); }
            exit();
        }
    }
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    /* DATA DELETION PROCESS */
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    $still_have_item = array();
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    $http_query = '';
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = (integer)$itemID;
		if (!$sql_op->delete('knowhow', "biblio_id=$itemID")) {
			$error_num++;
		} else {
			// write log
			utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'knowhow', $_SESSION['realname'].' DELETE knowhow data ('.$biblio_item_d[0].') with id ('.$itemID.')');
			// delete related data
			$sql_op->delete('knowhow_topic', "biblio_id=$itemID");
			$sql_op->delete('knowhow_author', "biblio_id=$itemID");
			$sql_op->delete('knowhow_attachment', "biblio_id=$itemID");
			$sql_op->delete('knowhow_tag', "biblio_id=$itemID");
			$sql_op->delete('knowhow_relation', "biblio_id=$itemID");
			$sql_op->delete('search_knowhow', "biblio_id=$itemID");
			// add to http query for UCS delete
			$http_query .= "itemID[]=$itemID&";
		}
    }

    // auto delete data on UCS if enabled
    if ($http_query && $sysconf['ucs']['enable'] && $sysconf['ucs']['auto_delete']) {
        echo '<script type="text/javascript">parent.ucsUpdate(\''.MODULES_WEB_ROOT_DIR.'knowhow/ucs_update.php\', \'nodeOperation=delete&'.$http_query.'\');</script>';
    }
    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\', {addData: \''.$_POST['lastQueryStr'].'\'});</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\', {addData: \''.$_POST['lastQueryStr'].'\'});</script>';
    }
    exit();
}
/* RECORD OPERATION END */

if (!$in_pop_up) {
/* search form */
?>
<fieldset class="menuBox">
<div class="menuBoxInner biblioIcon">
    <?php echo strtoupper(__('Bibliographic')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>knowhow/index.php?action=detail" class="headerText2"><?php echo __('Add New Regulation'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>knowhow/index.php" class="headerText2"><?php echo __('KH Regulation List'); ?></a>
    <?php
    // enable UCS?
    if ($sysconf['ucs']['enable']) {
    ?>
    <div class="marginTop"><a href="#" onclick="ucsUpload('<?php echo MODULES_WEB_ROOT_DIR; ?>knowhow/ucs_upload.php', serializeChbox('dataList'))" class="notAJAX ucsUpload"><?php echo __('Upload Selected KH Bibliographic data to Union Catalog Server*'); ?></a></div>
    <?php
    }
    ?>
    <hr />
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>knowhow/index.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
    <input type="text" name="keywords" id="keywords" size="30" />
    <select name="field"><option value="0"><?php echo __('All Fields'); ?></option><option value="title"><?php echo __('Title'); ?> </option><option value="subject"><?php echo __('Topics'); ?></option><option value="author"><?php echo __('Authors'); ?></option><option value="number"><?php echo __('Number'); ?></option><option value="notes"><?php echo __('Notes/Abstract'); ?></option></select>
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>
<?php
/* search form end */
}
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
    }
    /* RECORD FORM */
    // try query
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $_sql_rec_q = sprintf('SELECT b.* FROM knowhow AS b
        WHERE biblio_id=%d', $itemID);
    $rec_q = $dbs->query($_sql_rec_q);
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';
    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    $visibility = 'makeVisible';
    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        if (!$in_pop_up) {
            // form record id
            $form->record_id = $itemID;
        } else {
            $form->addHidden('updateRecordID', $itemID);
            $form->addHidden('itemCollID', $_POST['itemCollID']);
            $form->back_button = false;
        }
        // form record title
        $form->record_title = $rec_d['title'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
        // element visibility class toogle
        $visibility = 'makeHidden';

    }

    /* Form Element(s) */
    // regulation title
    $form->addTextField('textarea', 'title', __('Title').'*', $rec_d['title'], 'rows="1" style="width: 100%; overflow: auto;"');
    // regulation number
    $form->addTextField('text', 'number', __('Number'), $rec_d['number'], 'style="width: 100%;"');
    // regulation date issued
    $form->addDateField('create_date', __('Date of Issue'), $rec_d['create_date']?$rec_d['create_date']:date('Y-m-d'));
    // biblio topics
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'knowhow/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Subjects/Topics').'\')">'.__('Add Subject(s)').'</a></div>';
        $str_input .= '<iframe name="topicIframe" id="topicIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'knowhow/iframe_topic.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('Subject(s)'), $str_input);

    // biblio authors
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'knowhow/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Authors/Roles').'\')">'.__('Add Author(s)').'</a></div>';
        $str_input .= '<iframe name="authorIframe" id="authorIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'knowhow/iframe_author.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('Issuing Authority'), $str_input);
    // main text
    $form->addTextField('textarea', 'main_text', __('Main Text'), $rec_d['main_text'], 'style="width: 100%;" rows="2"');

	// link regulation
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'knowhow/pop_relation.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Relation').'\')">'.__('Add Relation(s)').'</a></div>';
        $str_input .= '<iframe name="relatedIframe" id="relatedIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'knowhow/iframe_relation.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('Related Regulation(s)'), $str_input);
	
    // biblio language
        // get language data related to this record from database
        $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
        $lang_options = array();
        while ($lang_d = $lang_q->fetch_row()) {
            $lang_options[] = array($lang_d[0], $lang_d[1]);
        }
    $form->addSelectList('languageID', __('Language'), $lang_options, $rec_d['language_id']);
    // english text
    $form->addTextField('textarea', 'en_text', __('English Translation'), $rec_d['en_text'], 'style="width: 100%;" rows="2"');
    // biblio note
    $form->addTextField('textarea', 'notes', __('Abstract/Notes'), $rec_d['notes'], 'style="width: 100%;" rows="2"');

    // knowhow_tag
        $str_input = '<div class="'.$visibility.'"><a class="notAJAX"  href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'knowhow/pop_tag.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Tag Term').'\')">'.__('Add tag(s)').'</a></div>';
        $str_input .= '<iframe name="tagIframe" id="tagIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'knowhow/iframe_tag.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('Tag(s)'), $str_input);

    // biblio file attachment
    $str_input = '<div class="'.$visibility.'"><a class="notAJAX" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'knowhow/pop_attach.php?biblioID='.$rec_d['biblio_id'].'\', 600, 300, \''.__('File Attachments').'\')">'.__('Add Attachment').'</a></div>';
    $str_input .= '<iframe name="attachIframe" id="attachIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MODULES_WEB_ROOT_DIR.'knowhow/iframe_attach.php?biblioID='.$rec_d['biblio_id'].'&block=1"></iframe>';
    $form->addAnything(__('File Attachment'), $str_input);

    // hidden type kh-item
    $form->addHidden('kh_type', 'reg');

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox" style="overflow: auto;">'
            .'<div style="float: left; width: 80%;">'.__('You are going to edit biblio data').' : <b>'.$rec_d['title'].'</b>  <br />'.__('Last Updated').$rec_d['last_update'].'</div>'; //mfc
            //if ($rec_d['image']) {
            //    if (file_exists(IMAGES_BASE_DIR.'docs/'.$rec_d['image'])) {
            //        $upper_dir = '';
            //        if ($in_pop_up) {
            //            $upper_dir = '../../';
            //        }
            //        echo '<div style="float: right;"><img src="'.$upper_dir.'../lib/phpthumb/phpThumb.php?src=../../images/docs/'.urlencode($rec_d['image']).'&w=53" style="border: 1px solid #999999" /></div>';
            //    }
            //}
        echo '</div>'."\n";
    }
    // print out the form object
    echo $form->printOut();
} else {
    require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_tokenizecql.inc.php';
    require MODULES_BASE_DIR.'knowhow/biblio_utils.inc.php';
    require LIB_DIR.'knowhow_list_model.inc.php';

    // number of records to show in list
    $biblio_result_num = ($sysconf['biblio_result_num']>100)?100:$sysconf['biblio_result_num'];

    // create datagrid
    $datagrid = new simbio_datagrid();

    // index choice
    if ($sysconf['index']['type'] == 'index' ||  $sysconf['index']['type'] == 'sphinx' ) {
        if ($sysconf['index']['type'] == 'sphinx') {
            require LIB_DIR.'sphinx/sphinxapi.php';
            require LIB_DIR.'biblio_list_sphinx.inc.php';
        } else {
            require LIB_DIR.'biblio_list_index.inc.php';
        }

        // table spec
        $table_spec = 'search_biblio AS `index` LEFT JOIN item ON `index`.biblio_id=item.biblio_id';

        if ($can_read AND $can_write) {
            $datagrid->setSQLColumn('index.biblio_id', 'index.title AS \''.__('Title').'\'', 'index.labels',
                'index.author',
                'index.isbn_issn AS \''.__('ISBN/ISSN').'\'',
                'IF(COUNT(item.item_id)>0, COUNT(item.item_id), \'<strong style="color: #f00;">'.__('None').'</strong>\') AS \''.__('Copies').'\'',
                'index.last_update AS \''.__('Last Update').'\'');
            $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
        } else {
            $datagrid->setSQLColumn('index.title AS \''.__('Title').'\'', 'index.author', 'index.labels',
                'index.isbn_issn AS \''.__('ISBN/ISSN').'\'',
                'IF(COUNT(item.item_id)>0, COUNT(item.item_id), \'<strong style="color: #f00;">'.__('None').'</strong>\') AS \''.__('Copies').'\'',
                'index.last_update AS \''.__('Last Update').'\'');
            $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
        }
        $datagrid->invisible_fields = array(1,2);
        $datagrid->setSQLorder('index.last_update DESC');

        // set group by
        $datagrid->sql_group_by = 'index.biblio_id';

    } else {
        require LIB_DIR.'knowhow_list.inc.php';

        // table spec
        $table_spec = 'knowhow ';

        if ($can_read AND $can_write) {
            $datagrid->setSQLColumn('knowhow.biblio_id', 'knowhow.biblio_id AS bid',
                'knowhow.title AS \''.__('Title').'\'',
                'knowhow.kh_type AS \''.__('Type').'\'',
                'knowhow.number AS \''.__('Reg No.').'\'',
                'knowhow.last_update AS \''.__('Last Update').'\'');
//            $datagrid->modifyColumnContent(2, 'callback{showTitleAuthors}');
        } else {
            $datagrid->setSQLColumn('knowhow.biblio_id AS bid', 'knowhow.title AS \''.__('Title').'\'',
                'knowhow.kh_type AS \''.__('Type').'\'',
                'knowhow.number AS \''.__('Reg No.').'\'',
                'IF(COUNT(item.item_id)>0, COUNT(item.item_id), \'<strong style="color: #f00;">'.__('None').'</strong>\') AS \''.__('Copies').'\'',
                'knowhow.last_update AS \''.__('Last Update').'\'');
            // modify column value
//            $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
        }
        $datagrid->invisible_fields = array(0);
        $datagrid->setSQLorder('knowhow.last_update DESC');

        // set group by
        $datagrid->sql_group_by = 'knowhow.biblio_id';
    }

	$stopwords= "@\sAnd\s|\sOr\s|\sNot\s|\sThe\s|\sDan\s|\sAtau\s|\sAn\s|\sA\s@i";

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $keywords = $dbs->escape_string(trim($_GET['keywords']));
		$keywords = preg_replace($stopwords,' ',$keywords);
        $searchable_fields = array('title', 'author', 'subject', 'number', 'notes', 'main_text', 'en_text', 'tag');
        if ($_GET['field'] != '0' AND in_array($_GET['field'], $searchable_fields)) {
            $field = $_GET['field'];
            $search_str = $field.'='.$keywords;
        } else {
            $search_str = '';
            foreach ($searchable_fields as $search_field) {
                $search_str .= $search_field.'='.$keywords.' OR ';
            }
            $search_str = substr_replace($search_str, '', -4);
        }
        $biblio_list = new biblio_list($dbs, $biblio_result_num);
        $criteria = $biblio_list->setSQLcriteria($search_str);
    }

    if (isset($criteria)) {
        $datagrid->setSQLcriteria('('.$criteria['sql_criteria'].') AND (kh_type=\'reg\')');
    } else {
        $datagrid->setSQLcriteria('(kh_type=\'reg\')');
	}
	
    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
    $datagrid->debug = true;

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, $biblio_result_num, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>'; //mfc
    }

    echo $datagrid_result;
}
/* main content end */
?>
