<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Some ajax security patches by Hendro Wicaksono (hendrowicaksono@yahoo.com)
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
 * You should have received a attachment of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Bibliographic attachment listing */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}


// required file
require '../../sysconfig.inc.php';
require '../member_session.inc.php';
session_start();

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $attachment_q = $dbs->query('SELECT att.*, f.* FROM knowhow_attachment AS att
        LEFT JOIN knowhow_files AS f ON att.file_id=f.file_id WHERE att.biblio_id='.$id.' AND att.access_type=\'public\' LIMIT 20');
    if ($attachment_q->num_rows < 1) {
        echo '<strong style="color: red; font-weight: bold;">'.__('No Attachment').'</strong>';
    } else {
        echo '<ul class="attachList">';
        while ($attachment_d = $attachment_q->fetch_assoc()) {
            // check member type privileges
            if ($attachment_d['access_limit']) {
                if (utility::isMemberLogin()) {
                    $allowed_mem_types = @unserialize($attachment_d['access_limit']);
                    if (!in_array($_SESSION['m_member_type_id'], $allowed_mem_types)) {
                        continue;
                    }
                } else {
                    continue;
                }
            }
            if ($attachment_d['mime_type'] == 'application/pdf') {
                echo '<li style="list-style-image: url(images/labels/ebooks.png)"><strong><a href="#" title="Read the document online" onclick="openHTMLpop(\'index.php?p=fstream&type=edocs&fid='.$attachment_d['file_id'].'&bid='.$attachment_d['biblio_id'].'\', 830, 500, \''.$attachment_d['file_title'].'\')">'.$attachment_d['file_title'].'</a></strong>';
                echo '</li>';
            } else if (preg_match('@(video|audio)/.+@i', $attachment_d['mime_type'])) {
                echo '<li style="list-style-image: url(images/labels/auvi.png)"><strong><a href="#" title="Click to Play, Listen or View" onclick="openHTMLpop(\'index.php?p=multimediastream&type=edocs&fid='.$attachment_d['file_id'].'&bid='.$attachment_d['biblio_id'].'\', 400, 300, \''.$attachment_d['file_title'].'\')">'.$attachment_d['file_title'].'</a></strong>';
                echo '</li>';
            } else if (preg_match('@^(http|ftp|https)\:\/\/.+@i', $attachment_d['file_title'])) {
                echo '<li style="list-style-image: url(images/labels/url.png)"><strong><a href="'.$attachment_d['file_title'].'" target="_blank" title="Open URL">'.$attachment_d['file_title'].'</a></strong>';
                echo '</li>';
            } else {
                echo '<li style="list-style-image: url(images/labels/ebooks.png)"><strong><a title="Click To View File" href="index.php?p=fstream&type=edocs&fid='.$attachment_d['file_id'].'&bid='.$attachment_d['biblio_id'].'" target="_blank">'.$attachment_d['file_title'].'</a></strong>';
            }
        }
        echo '</ul>';
    }
}
?>
