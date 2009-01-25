<?php
    /*
    RosCMS - ReactOS Content Management System
    Copyright (C) 2009  Danny G�tte <dangerground@web.de>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
    */


/**
 * class Admin_Groups
 * 
 */
class Admin_Groups extends Admin
{



  /**
   *
   *
   * @access protected
   */
  protected function showNew( )
  {
    $stmt=&DBConnection::getInstance()->prepare("SELECT id, name, description FROM ".ROSCMST_RIGHTS." ORDER BY name ASC");
    $stmt->execute();
    $rights=$stmt->fetchAll(PDO::FETCH_ASSOC);

    echo_strip('
      <h2>Create new Group</h2>
      <form onsubmit="return false;">
        <fieldset>
          <legend>Group Data</legend>
          <label for="group_sec">Security Level</label>
          <select id="group_sec" name="group_sec">
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
          </select>
          <br />

          <label for="group_name">Name</label>
          <input id="group_name" name="group_name" maxlength="100" />
          <br />

          <label for="group_short">Short Name (Identifier)</label>
          <input id="group_short" name="group_short" maxlength="50" />
          <br />

          <label for="group_desc">Description</label>
          <input id="group_desc" name="group_desc" maxlength="255" />
        </fieldset>
        <br />
        <fieldset>
          <legend>configure group access rights</legend>
          <table>
            <tr>
              <th>ACL Name</th>');
    foreach ($rights as $right) {
      echo '<th style="vertical-align:bottom;" title="'.$right['name'].': '.$right['description'].'"><img src="?page=presentation&amp;type=vtext&amp;text='.$right['name'].'" alt="'.$right['name'].'" /></th>';
    }
    echo '</tr>';

    $stmt=&DBConnection::getInstance()->prepare("SELECT id, name, description FROM ".ROSCMST_ACCESS." ORDER BY name ASC");
    $stmt->execute();
    while ($access = $stmt->fetch(PDO::FETCH_ASSOC)) {
      echo_strip('
        <tr title="'.htmlspecialchars($access['description']).'">
          <td>'.htmlspecialchars($access['name']).'</td>');

      foreach ($rights as $right) {
        echo '<td title="'.$access['name'].'--'.$access['name'].': '.$access['description'].'"><input type="checkbox" value="1" name="valid'.$access['id'].'_'.$right['id'].'" /></td>';
      }
      echo '</tr>';
    }

    echo_strip('
          </table>
        </fieldset>
        <br />
        <fieldset>
          <legend>Area Protection List (APL)</legend>
          <table>
            <tr>
              <th>ACL Name</th>
              <th>Status</th>');

    $stmt=&DBConnection::getInstance()->prepare("SELECT id, name, description FROM ".ROSCMST_AREA." ORDER BY name ASC");
    $stmt->execute();
    while ($area = $stmt->fetch(PDO::FETCH_ASSOC)) {
      echo_strip('
        <tr>
          <td title="'.$area['description'].'"><label for="area'.$area['id'].'">'.$area['name'].'</label></td>
          <td><input type="checkbox" value="1" name="area'.$area['id'].'" id="area'.$area['id'].'" /></td>
        </tr>');
    }

    echo_strip('
        </fieldset>
        <button onclick="'."submitNew('group')".'">Create new Group</button>
      </form>
    ');
  } // end of member function showNew



  /**
   *
   *
   * @access protected
   */
  protected function submitNew( )
  {
    $success = true;
  
    // try to insert new access list
    $stmt=&DBConnection::getInstance()->prepare("INSERT INTO ".ROSCMST_GROUPS." (name, name_short, description, security_level, visible) VALUES (:name, :short, :description, :security_level, FALSE)");
    $stmt->bindParam('name',$_POST['group_name'],PDO::PARAM_STR);
    $stmt->bindParam('short',$_POST['group_short'],PDO::PARAM_STR);
    $stmt->bindParam('description',$_POST['group_desc'],PDO::PARAM_STR);
    $stmt->bindParam('security_level',$_POST['group_sec'],PDO::PARAM_INT);
    if ($stmt->execute()) {
    
      // check for new access list id
      $stmt=&DBConnection::getInstance()->prepare("SELECT id FROM ".ROSCMST_GROUPS." WHERE name=:name");
      $stmt->bindParam('name',$_POST['group_name'],PDO::PARAM_STR);
      $stmt->execute();
      $group_id = $stmt->fetchColumn();
      if ($group_id !== false) {

        $stmt=&DBConnection::getInstance()->prepare("SELECT id, name, description FROM ".ROSCMST_RIGHTS." ORDER BY name ASC");
        $stmt->execute();
        $rights=$stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_acl=&DBConnection::getInstance()->prepare("INSERT INTO ".ROSCMST_ACL." (access_id, group_id, right_id) VALUES (:access_id,:group_id,:right_id)");
        $stmt_acl->bindParam('group_id',$group_id,PDO::PARAM_INT);
        $stmt_apl=&DBConnection::getInstance()->prepare("INSERT INTO ".ROSCMST_AREA_ACCESS." (area_id, group_id) VALUES (:area_id,:group_id)");
        $stmt_apl->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
        foreach ($_POST as $item=>$val) {
        
          // insert ACL
          if (strpos($item,'valid')===0) {
            $item = substr($item, 5);
            $id = explode('_',$item);
            if($id[0] > 0 && $id[1] > 0 && $val=='true') {
              $stmt_acl->bindParam('right_id',$id[1],PDO::PARAM_INT);
              $stmt_acl->bindParam('access_id',$id[0],PDO::PARAM_INT);
              $success = $success && $stmt_acl->execute();
            }
          }

          // insert APL
          elseif (strpos($item,'area')===0 && $val=='true') {
            $id = substr($item, 4);
            if($id > 0) {
              $stmt_apl->bindParam('area_id',$id,PDO::PARAM_INT);
              $success = $success && $stmt_apl->execute();
            }
          }
        } // end foreach
      } // end got list id
      else {
        $success = false;
      }
    } // end list inserted
    else {
      $success = false;
    }

    // give the user a success or failure message
    if ($success) {
      echo_strip('New Group was created successfully');
    }
    else {
      echo_strip('Error, while creating new Group');
    }
  } // end of member function submitNew



  /**
   *
   *
   * @access protected
   */
  protected function showSearch( )
  {
    echo_strip('
      <h2>Select Group to '.($_GET['for']=='edit' ? 'edit' : 'delete').'</h2>
      <form onsubmit="return false;">
        <select name="group" id="group">
          <option value="0">&nbsp;</option>');

    $stmt=&DBConnection::getInstance()->prepare("SELECT id, name, description FROM ".ROSCMST_GROUPS." ORDER BY name ASC");
    $stmt->execute();
    while ($group = $stmt->fetch(PDO::FETCH_ASSOC)) {
      echo '<option value="'.$group['id'].'" title="'.$group['description'].'">'.$group['name'].'</option>';
    }

    echo_strip('
        </select>
        <button onclick="'."submitSearch('group','".($_GET['for'] == 'edit' ? 'edit' : 'delete')."')".'">go on</button>
      </form>');
  }



  /**
   *
   *
   * @access protected
   */
  protected function submitSearch( )
  {
    // show edit / delete form, if entry was selected
    if ($_POST['group'] > 0) {
      if ($_GET['for'] == 'edit') {
        self::showEdit();
      }
      elseif ($_GET['for'] == 'delete') {
        self::showDelete();
      }
    }

    // show search again
    else {
      self::showSearch();
    }
  }



  /**
   *
   *
   * @access protected
   */
  protected function showEdit( )
  {
    $stmt=&DBConnection::getInstance()->prepare("SELECT id, name, description FROM ".ROSCMST_RIGHTS." ORDER BY name ASC");
    $stmt->execute();
    $rights=$stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt=&DBConnection::getInstance()->prepare("SELECT name, name_short, description, id, security_level FROM ".ROSCMST_GROUPS." WHERE id=:acl_id");
    $stmt->bindParam('acl_id',$_POST['group'],PDO::PARAM_INT);
    $stmt->execute();
    $group = $stmt->fetchOnce(PDO::FETCH_ASSOC);

    echo_strip('
      <h2>Edit Group</h2>
      <form onsubmit="return false;">
        <fieldset>
          <legend>Group Data</legend>
          <input type="hidden" name="group_id" id="group_id" value="'.$group['id'].'" />

          <label for="group_sec">Security Level</label>
          <select id="group_sec" name="group_sec">
            <option value="0"'.($group['security_level'] == 0 ? ' selected="selected"' : '').'>0</option>
            <option value="1"'.($group['security_level'] == 1 ? ' selected="selected"' : '').'>1</option>
            <option value="2"'.($group['security_level'] == 2 ? ' selected="selected"' : '').'>2</option>
            <option value="3"'.($group['security_level'] == 3 ? ' selected="selected"' : '').'>3</option>
          </select>
          <br />

          <label for="group_name">Name</label>
          <input id="group_name" name="group_name" maxlength="100" value="'.htmlspecialchars($group['name']).'" />
          <br />

          <label for="group_short">Short Name (Identifier)</label>
          <input id="group_short" name="group_short" maxlength="50" value="'.htmlspecialchars($group['name_short']).'" />
          <br />

          <label for="group_desc">Description</label>
          <input id="group_desc" name="group_desc" maxlength="255" value="'.htmlspecialchars($group['description']).'"  />
        </fieldset>
        <br />
        <fieldset>
          <legend>configure group access rights</legend>
          <table>
            <tr>
              <th>ACL Name</th>');
    foreach ($rights as $right) {
      echo '<th style="vertical-align:bottom;" title="'.$right['name'].': '.$right['description'].'"><img src="?page=presentation&amp;type=vtext&amp;text='.$right['name'].'" alt="'.$right['name'].'" /></th>';
    }
    echo '</tr>';

    // for usage in loop
    $stmt_is=&DBConnection::getInstance()->prepare("SELECT TRUE FROM ".ROSCMST_ACL." WHERE group_id=:group_id AND right_id=:right_id AND access_id=:access_id LIMIT 1");
    $stmt_is->bindParam('group_id',$group['id'],PDO::PARAM_INT);

    $stmt=&DBConnection::getInstance()->prepare("SELECT id, name, description FROM ".ROSCMST_ACCESS." ORDER BY name ASC");
    $stmt->execute();
    while ($access = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $stmt_is->bindParam('access_id',$access['id'],PDO::PARAM_INT);
      echo_strip('
        <tr title="'.htmlspecialchars($access['description']).'">
          <td>'.htmlspecialchars($access['name']).'</td>');
      foreach ($rights as $right) {
        $stmt_is->bindParam('right_id',$right['id'],PDO::PARAM_INT);
        $stmt_is->execute();
        $is = $stmt_is->fetchColumn();

        echo '<td title="'.$access['name'].'--'.$right['name'].': '.$right['description'].'"><input type="checkbox" value="1" name="valid'.$access['id'].'_'.$right['id'].'" '.($is ? 'checked="checked"' : '').' /></td>';
      }
      echo '</tr>';
    }

    echo_strip('
          </table>
        </fieldset>
        <br />
        <fieldset>
          <legend>Area Protection List (APL)</legend>
          <table>
            <tr>
              <th>ACL Name</th>
              <th>Status</th>');
              
    // for usage in loop
      $stmt_is=&DBConnection::getInstance()->prepare("SELECT TRUE FROM ".ROSCMST_AREA_ACCESS." WHERE group_id=:group_id AND area_id=:area_id LIMIT 1");
      $stmt_is->bindParam('group_id',$group['id'],PDO::PARAM_INT);

    $stmt=&DBConnection::getInstance()->prepare("SELECT id, name, description FROM ".ROSCMST_AREA." ORDER BY name ASC");
    $stmt->execute();
    while ($area = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $stmt_is->bindParam('area_id',$area['id'],PDO::PARAM_INT);
      $stmt_is->execute();
      $is = $stmt_is->fetchColumn();

      echo_strip('
        <tr>
          <td title="'.$area['description'].'"><label for="area'.$area['id'].'">'.$area['name'].'</label></td>
          <td><input type="checkbox" value="1" name="area'.$area['id'].'" id="area'.$area['id'].'"'.($is ? ' checked="checked"' : '').' /></td>
        </tr>');
    }

    echo_strip('
        </fieldset>
        <button onclick="'."submitEdit('group')".'">Edit Group</button>
      </form>
    ');
  }



  /**
   *
   *
   * @access protected
   */
  protected function submitEdit( )
  {
    $success = true;

    // try to insert new access list
    $stmt=&DBConnection::getInstance()->prepare("UPDATE ".ROSCMST_GROUPS." SET name=:name, name_short=:short, description=:description, security_level=:sec_level WHERE id=:group_id");
    $stmt->bindParam('name',$_POST['group_name'],PDO::PARAM_STR);
    $stmt->bindParam('short',$_POST['group_short'],PDO::PARAM_STR);
    $stmt->bindParam('description',$_POST['group_desc'],PDO::PARAM_STR);
    $stmt->bindParam('sec_level',$_POST['group_sec'],PDO::PARAM_STR);
    $stmt->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
    $success = $success && $stmt->execute();

    $stmt=&DBConnection::getInstance()->prepare("DELETE FROM ".ROSCMST_ACL." WHERE group_id=:group_id");
    $stmt->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
    $success = $success && $stmt->execute();

    $stmt=&DBConnection::getInstance()->prepare("DELETE FROM ".ROSCMST_AREA_ACCESS." WHERE group_id=:group_id");
    $stmt->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
    $success = $success && $stmt->execute();

    if ($success) {
      $stmt_acl=&DBConnection::getInstance()->prepare("INSERT INTO ".ROSCMST_ACL." (access_id, group_id, right_id) VALUES (:access_id,:group_id,:right_id)");
      $stmt_acl->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
      $stmt_apl=&DBConnection::getInstance()->prepare("INSERT INTO ".ROSCMST_AREA_ACCESS." (area_id, group_id) VALUES (:area_id,:group_id)");
      $stmt_apl->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
      foreach ($_POST as $item=>$val) {

        // insert ACL
        if (strpos($item,'valid')===0) {
          $item = substr($item, 5);
          $id = explode('_',$item);
          if($id[0] > 0 && $id[1] > 0 && $val=='true') {
            $stmt_acl->bindParam('right_id',$id[1],PDO::PARAM_INT);
            $stmt_acl->bindParam('access_id',$id[0],PDO::PARAM_INT);
            $success = $success && $stmt_acl->execute();
          }
        }

        // insert APL
        elseif (strpos($item,'area')===0 && $val=='true') {
          $id = substr($item, 4);
          if($id > 0) {
            $stmt_apl->bindParam('area_id',$id,PDO::PARAM_INT);
            $success = $success && $stmt_apl->execute();
          }
        }
      }
    }

    // give the user a success or failure message
    if ($success) {
      echo 'Group was edited successfully';
    }
    else {
      echo 'Error, while editing Group';
    }
  }



  /**
   *
   *
   * @access protected
   */
  protected function showDelete( )
  {
    $stmt=&DBConnection::getInstance()->prepare("SELECT name, description, id FROM ".ROSCMST_GROUPS." WHERE id=:group_id");
    $stmt->bindParam('group_id',$_POST['group'],PDO::PARAM_INT);
    $stmt->execute();
    $group = $stmt->fetchOnce(PDO::FETCH_ASSOC);

    echo_strip('
      <form onsubmit="return false;">
        <div>
          <input type="hidden" name="group_id" id="group_id" value="'.$group['id'].'" />

          Do you really want to delete the Group &quot;<span title="'.$group['description'].'">'.$group['name'].'</span>&quot; ?
          <button style="color: red;" onclick="'."submitDelete('group')".'" name="uaq" value="yes">Yes, Delete it.</button>
          <button style="color: green;" name="uaq" value="no">No</button>
        </div>
      </form>');
  }



  /**
   *
   *
   * @access protected
   */
  protected function submitDelete( )
  {
    $success = true;

    // delete acl
    $stmt=&DBConnection::getInstance()->prepare("DELETE FROM ".ROSCMST_GROUPS." WHERE id=:group_id");
    $stmt->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
    $success = $success && $stmt->execute();

    // delete rights list
    if ($success) {
      $stmt=&DBConnection::getInstance()->prepare("DELETE FROM ".ROSCMST_ACL." WHERE group_id=:group_id");
      $stmt->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
      $success = $success && $stmt->execute();

      $stmt=&DBConnection::getInstance()->prepare("DELETE FROM ".ROSCMST_ACL." WHERE group_id=:group_id");
      $stmt->bindParam('group_id',$_POST['group_id'],PDO::PARAM_INT);
      $success = $success && $stmt->execute();
    }

    // status message
    if ($success) {
      echo 'Group was deleted successfully';
    }
    else {
      echo 'Error, while deleting Group';
    }
  }



} // end of Admin_Groups
?>