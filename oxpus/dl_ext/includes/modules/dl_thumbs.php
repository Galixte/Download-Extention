<?php

/**
*
* @package phpBB Extension - Oxpus Downloads
* @copyright (c) 2014 OXPUS - www.oxpus.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/*
* connect to phpBB
*/
if ( !defined('IN_PHPBB') )
{
	exit;
}

$cat_auth = array();
$cat_auth = \oxpus\dl_ext\includes\classes\ dl_auth::dl_cat_auth($cat_id);

/*
* default entry point for download details
*/
$dl_files = array();
$dl_files = \oxpus\dl_ext\includes\classes\ dl_files::all_files(0, '', 'ASC', '', $df_id, 0, '*');

/*
* check the permissions
*/
$check_status = array();
$check_status = \oxpus\dl_ext\includes\classes\ dl_status::status($df_id, $this->helper, $ext_path_images);

if (!$dl_files['id'])
{
	trigger_error('DL_NO_PERMISSION');
}

// Check saved thumbs
$sql = 'SELECT * FROM ' . DL_IMAGES_TABLE . '
	WHERE dl_id = ' . (int) $df_id;
$result = $this->db->sql_query($sql);
$total_images = $this->db->sql_affectedrows($result);

if ($total_images)
{
	$this->template->assign_var('S_DL_POPUPIMAGE', true);

	$thumbs_ary = array();

	while ($row = $this->db->sql_fetchrow($result))
	{
		$thumbs_ary[] = $row;
	}
}

$this->db->sql_freeresult($result);

$inc_module = true;
page_header($this->user->lang['DOWNLOADS'] . ' - ' . $dl_files['description'] . ' - ' . $this->user->lang['DL_EDIT_THUMBS']);

$img_id			= $this->request->variable('img_id', 0);
$edit_img_link	= $this->request->variable('edit_img_link', '', true);
$img_title		= $this->request->variable('img_title', '', true);
$action			= $this->request->variable('action', '', true);

$edit_img_title	= '';

if ($action == 'delete' && $img_id && $df_id)
{
	$sql = 'SELECT img_name FROM ' . DL_IMAGES_TABLE . '
		WHERE img_id = ' . (int) $img_id . '
			AND dl_id = ' . (int) $df_id;
	$result = $this->db->sql_query($sql);
	$img_link = $this->db->sql_fetchfield('img_name');
	$this->db->sql_freeresult($result);

	@unlink(DL_EXT_THUMBS_FOLDER . $img_link);

	$sql = 'DELETE FROM ' . DL_IMAGES_TABLE . '
		WHERE img_id = ' . (int) $img_id . '
			AND dl_id = ' . (int) $df_id;
	$this->db->sql_query($sql);

	$action = '';
}

if ($action == 'edit' && $img_id && $df_id)
{
	$sql = 'SELECT img_name, img_title FROM ' . DL_IMAGES_TABLE . '
		WHERE img_id = ' . (int) $img_id . '
			AND dl_id = ' . (int) $df_id;
	$result = $this->db->sql_query($sql);
	$row = $this->db->sql_fetchrow($result);
	$edit_img_link = $row['img_name'];
	$edit_img_title = $row['img_title'];
	$this->db->sql_freeresult($result);

	$action = '';
}

if ($submit && !$action)
{
	if (!check_form_key('dl_thumbs'))
	{
		trigger_error('FORM_INVALID');
	}

	$this->user->add_lang('posting');

	if (!class_exists('fileupload'))
	{
		include($this->root_path . 'includes/functions_upload.' . $this->php_ext);
	}

	$fileupload = new fileupload();
	$fileupload->fileupload('');

	if ($this->config['dl_thumb_fsize'] && $index[$cat_id]['allow_thumbs'])
	{
		$thumb_file = $fileupload->form_upload('img_link');

		$thumb_size = $thumb_file->filesize;
		$thumb_temp = $thumb_file->filename;
		$thumb_name = $thumb_file->realname;

		$error_count = sizeof($thumb_file->error);
		if ($error_count > 1 && $thumb_file->uploadname)
		{
			$thumb_file->remove();
			trigger_error(implode('<br />', $thumb_file->error), E_USER_ERROR);
		}

		$thumb_file->error = array();

		if ($thumb_name)
		{
			$pic_size = @getimagesize($thumb_temp);
			$pic_width = $pic_size[0];
			$pic_height = $pic_size[1];

			if (!$pic_width || !$pic_height)
			{
				trigger_error($this->user->lang['DL_UPLOAD_ERROR'], E_USER_ERROR);
			}

			if ($pic_width > $this->config['dl_thumb_xsize'] || $pic_height > $this->config['dl_thumb_ysize'] || (sprintf("%u", @filesize($thumb_temp) > $this->config['dl_thumb_fsize'])))
			{
				trigger_error($this->user->lang['DL_THUMB_TO_BIG'], E_USER_ERROR);
			}
		}
	}

	if (isset($thumb_name) && $thumb_name != '')
	{
		$cur_time = time();
		$thumb_tmp_link = $cur_time . '_' . $thumb_name;

		while (!file_exists(DL_EXT_THUMBS_FOLDER . $thumb_tmp_link))
		{
			$thumb_file->realname = $thumb_tmp_link;
			$dest_folder = str_replace($this->root_path, '', substr(DL_EXT_THUMBS_FOLDER, 0, -1));
			$thumb_file->move_file($dest_folder, false, false, CHMOD_ALL);
			@chmod($thumb_file->destination_file, 0777);

			$cur_time = time();
			$thumb_tmp_link = $cur_time . '_' . $thumb_name;
		}

		$img_link = $cur_time . '_' . $thumb_name;;

		if ($img_id)
		{
			$sql = 'SELECT img_name FROM ' . DL_IMAGES_TABLE . ' WHERE img_id = ' . (int) $img_id;
			$result = $this->db->sql_query($sql);
			$old_img_link = $this->db->sql_fetchfield('img_name');
			$this->db->sql_freeresult($result);

			if ($old_img_link != '')
			{
				@unlink(DL_EXT_THUMBS_FOLDER . $old_img_link);
			}
		}
	}
	else
	{
		$img_link = $edit_img_link;
	}

	$thumb_message = '<br />' . $this->user->lang['DL_THUMB_UPLOAD'];

	if ($img_id)
	{
		$sql_array = array(
				'img_name'		=> $img_link,
				'img_title'		=> $img_title,
		);

		$sql = 'UPDATE ' . DL_IMAGES_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_array) . ' WHERE img_id = ' . (int) $img_id . ' AND dl_id = ' . (int) $df_id;
		$this->db->sql_query($sql);
	}
	else
	{
		$sql_array = array(
				'img_id'		=> $img_id,
				'dl_id'			=> $df_id,
				'img_name'		=> $img_link,
				'img_title'		=> $img_title,
		);

		$sql = 'INSERT INTO ' . DL_IMAGES_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_array);
		$this->db->sql_query($sql);
	}

	meta_refresh(3, $this->helper->route('dl_ext_controller', array('view' => 'thumbs', 'df_id' => $df_id, 'cat' => $cat_id)));

	$message = $thumb_message . '<br /><br />' . sprintf($this->user->lang['CLICK_RETURN_THUMBS'], '<a href="' . $this->helper->route('dl_ext_controller', array('view' => 'thumbs', 'df_id' => $df_id, 'cat' => $cat_id)) . '">', '</a>');

	trigger_error($message);
}

$this->template->set_filenames(array(
	'body' => 'dl_thumbs_body.html')
);

add_form_key('dl_thumbs');

$s_hidden_fields = array(
	'img_id'		=> $img_id,
	'edit_img_link'	=> $edit_img_link,
	'df_id'			=> $df_id,
);

$thumb_max_size = sprintf($this->user->lang['DL_THUMB_DIM_SIZE'], $this->config['dl_thumb_xsize'], $this->config['dl_thumb_ysize'], \oxpus\dl_ext\includes\classes\ dl_format::dl_size($this->config['dl_thumb_fsize']));

$sql = 'SELECT * FROM ' . DL_IMAGES_TABLE . '
	WHERE dl_id = ' . (int) $df_id;
$result = $this->db->sql_query($sql);

while ($row = $this->db->sql_fetchrow($result))
{
	$this->template->assign_block_vars('thumbnails', array(
		'IMG_LINK'	=> DL_EXT_THUMBS_WEB_FOLDER . str_replace(" ", "%20", $row['img_name']),
		'IMG_TITLE'	=> $row['img_title'],

		'U_DELETE'	=> $this->helper->route('dl_ext_controller', array('view' => 'thumbs', 'action' => 'delete', 'cat_id' => $cat_id, 'df_id' => $df_id, 'img_id' => $row['img_id'])),
		'U_EDIT'	=> $this->helper->route('dl_ext_controller', array('view' => 'thumbs', 'action' => 'edit', 'cat_id' => $cat_id, 'df_id' => $df_id, 'img_id' => $row['img_id'])),
	));
}
$this->db->sql_freeresult($result);

$this->template->assign_vars(array(
	'DESCRIPTION'		=> $description,
	'MINI_IMG'			=> $mini_icon,
	'HACK_VERSION'		=> $hack_version,
	'STATUS'			=> $status,
	'DL_THUMB_MAX_SIZE'	=> $thumb_max_size,

	'EDIT_IMG_TITLE'	=> $edit_img_title,

	'ENCTYPE'			=> 'enctype="multipart/form-data"',

	'S_FORM_ACTION'		=> $this->helper->route('dl_ext_controller', array('view' => 'thumbs')),
	'S_HIDDEN_FIELDS'	=> build_hidden_fields($s_hidden_fields))
);
