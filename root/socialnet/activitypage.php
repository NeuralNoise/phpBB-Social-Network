<?php
/**
 *
 * @package phpBB Social Network
 * @version 0.6.3
 * @copyright (c) phpBB Social Network Team 2010-2012 http://phpbbsocialnetwork.com
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

if (!defined('SOCIALNET_INSTALLED') || !defined('IN_PHPBB'))
{
	/**
	 * @ignore
	 */
	define('IN_PHPBB', true);
	/**
	 * @ignore
	 */
	define('SN_LOADER', 'activitypage');
	define('SN_AP', true);
	$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './../';
	$phpEx = substr(strrchr(__FILE__, '.'), 1);
	include_once($phpbb_root_path . 'common.' . $phpEx);
	include_once($phpbb_root_path . 'includes/functions_display.' . $phpEx);

	// Start session management
	$user->session_begin(false);
	$auth->acl($user->data);
	$user->setup('viewforum');
}

if (!class_exists('socialnet_activitypage'))
{
	class socialnet_activitypage
	{
		var $p_master = null;
		var $friends_entry = array();

		function socialnet_activitypage(&$p_master = null)
		{
			$this->p_master =& $p_master;
		}

		function init()
		{
			global $phpEx, $user, $template, $phpbb_root_path, $db, $socialnet;

			$template_vars = array();

			$on_login = false;
			if ($this->p_master->script_name == 'activitypage')
			{
				$on_login = $this->p_master->block('login');
			}

			if ($this->p_master->script_name == 'activitypage' && !$on_login)
			{
				$mode = request_var('mode', 'view_main');

				switch ($mode)
				{
				case 'view_suggestions':

					$this->p_master->fms_users(array_merge(array(
						'mode'				 => 'suggestionfull',
						'mode_short'		 => 'suggestion',
						'slider'			 => false,
						'user_id'			 => $user->data['user_id'],
						'limit'				 => 50,
						'fmsf'				 => 0,
						'avatar_size'		 => 50,
						'add_friend_link'	 => true
					), $this->p_master->fms_users_sqls('suggestion', $user->data['user_id'])));

					break;

				case 'view_main':

					$last_entry_time = request_var('lEntryTime', 0);

					//$a_ap_entries = $this->ap_load_entries($last_entry_time, 15);
					$a_ap_entries = $this->p_master->activity->get($last_entry_time, 15);
					foreach ($a_ap_entries['entries'] as $idx => $a_ap_entry)
					{
						$template->assign_block_vars('ap_entries', $a_ap_entry);
					}

					$template_vars = array_merge($template_vars, array(
						'B_SN_AP_MORE_ENTRIES'	 => $a_ap_entries['more'],
					));

					break;

				case 'users_autocomplete':

					$socialnet->users_autocomplete();

					break;

				case 'search':

					$username = utf8_clean_string(request_var('username', '', true));

					$sql = 'SELECT user_id
        		          FROM ' . USERS_TABLE . '
        		            WHERE username_clean LIKE "%' . $username . '%"';
					$result = $db->sql_query($sql);
					$search_user_id = $db->sql_fetchfield('user_id');
					$db->sql_freeresult($result);

					redirect(append_sid("{$phpbb_root_path}profile.$phpEx", 'u=' . $search_user_id));

					break;
				}

				$template_vars = array_merge($template_vars, array(
					'S_MY_USERNAME'		 => $user->data['username'],
					'S_MY_USER_AVATAR'	 => $this->p_master->get_user_avatar_resized($user->data['user_avatar'], $user->data['user_avatar_type'], $user->data['user_avatar_width'], $user->data['user_avatar_height'], 50),
					'U_VIEW_SUGGESTIONS' => append_sid("activitypage.$phpEx", 'mode=view_suggestions'),
					'U_MANAGE_FRIEND'	 => append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra'),
					'U_ADD_FRIEND'		 => append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra'),
					'U_EDIT_MY_PROFILE'	 => append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=profile'),
					'U_MY_USERNAME_LINK' => append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&amp;u=' . $user->data['user_id']),
					'S_SN_AP_' . strtoupper($mode)					 => true,
					'USER_ID'			 => $user->data['user_id'],
				));
			}

			$ap_enabled = true;
			// Generate Activity page Comments counts.
			if ($user->data['is_registered'])
			{
				$my_friends = $this->p_master->friends['user_id'];

				if ($my_friends)
				{
					$sql = 'SELECT COUNT(entry_id) as count
			              FROM ' . SN_ENTRIES_TABLE . '
			                WHERE entry_time > ' . $user->data['user_lastvisit'] . '
												AND entry_type = ' . SN_TYPE_NEW_STATUS_COMMENT . '
			                	AND ' . $db->sql_in_set('user_id', $my_friends);
					$result = $db->sql_query($sql);

					$template_vars = array_merge($template_vars, array(
						'SN_AP_NEW_COMMENTS_COUNT'	 => $db->sql_fetchfield('count', false, $result),
					));
					$db->sql_freeresult($result);
				}
			}
			else
			{
				$ap_enabled = !$this->p_master->config['ap_hide_for_guest'];
			}

			$template_vars = array_merge($template_vars, array(
				'SN_MODULE_ACTIVITYPAGE_ENABLED' => $ap_enabled,
			));

			$template->assign_vars($template_vars);
		}

		function load($mode)
		{
			global $socialnet_root_path, $phpEx, $socialnet, $template, $phpbb_root_path;

			switch ($mode)
			{
			case 'onlineUsers':

				$this->p_master->online_users(true);

				break;

			case 'snApOlderEntries':

				$last_entry_time = request_var('lEntryTime', 0);

				//$a_ap_entries = $this->ap_load_entries($last_entry_time, 15);
				$a_ap_entries = $this->p_master->activity->get($last_entry_time, 15);

				foreach ($a_ap_entries['entries'] as $idx => $a_ap_entry)
				{
					$template->assign_block_vars('ap_entries', $a_ap_entry);
				}

				$return = array();
				$return['more'] = $a_ap_entries['more'];

				$template->assign_vars(array(
					'B_SN_AP_MORE_ENTRIES'	 => $a_ap_entries['more'],
					'B_SN_AP_MORE_LOAD'		 => true,
				));

				$template->set_filenames(array('body' => 'socialnet/activitypage_body_entries.html'));

				$return['content'] = $this->p_master->get_page();

				header('Content-type: application/json');
				header("Cache-Control: no-cache, must-revalidate");
				header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
				die(json_encode($return));

				break;

			case 'snApNewestEntries':

				$last_entry_time = request_var('lEntryTime', 0);

				//$a_ap_entries = $this->ap_load_entries($last_entry_time, 15, false);
				$a_ap_entries = $this->p_master->activity->get($last_entry_time, 15, false);

				foreach ($a_ap_entries['entries'] as $idx => $a_ap_entry)
				{
					$template->assign_block_vars('ap_entries', $a_ap_entry);
				}

				$return = array();
				$return['more'] = $a_ap_entries['more'];

				$template->assign_vars(array(
					'B_SN_AP_MORE_ENTRIES'	 => $a_ap_entries['more'],
					'B_SN_AP_MORE_LOAD'		 => true,
				));

				$template->set_filenames(array('body' => 'socialnet/activitypage_body_entries.html'));

				$return['content'] = $this->p_master->get_page();

				header('Content-type: application/json');
				header("Cache-Control: no-cache, must-revalidate");
				header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
				die(json_encode($return));

				break;
			}

		}

		/**
		 * Load entries for Activity page
		 *
		 * @param integer $last_entry_time The last loaded entry
		 * @param integet $entries_limit How many entries will be loaded
		 * @param boolean $older Load older entries (true), load newer entries (false)
		 * @return array pole plne dat se vstupy na AP
		 */
		function ap_load_entries($last_entry_time, $entries_limit = 15, $older = true)
		{
			global $db, $user, $cache, $config;

			$avail_entry_types = array();
			if (in_array('userstatus', $this->p_master->existing))
			{
				$avail_entry_types[] = SN_TYPE_NEW_STATUS;
			}
			if ($config['ap_show_new_friendships'])
			{
				$avail_entry_types[] = SN_TYPE_NEW_FRIENDSHIP;
			}
			if ($config['ap_show_profile_updated'])
			{
				$avail_entry_types[] = SN_TYPE_PROFILE_UPDATED;
			}
			if ($config['ap_show_new_family'])
			{
				$avail_entry_types[] = SN_TYPE_NEW_FAMILY;
			}
			if ($config['ap_show_new_relationship'])
			{
				$avail_entry_types[] = SN_TYPE_NEW_RELATIONSHIP;
			}
			$avail_entry_types[] = SN_TYPE_EMOTE;

			$my_friends = $this->p_master->friends['user_id'];
			$my_friends[] = $user->data['user_id'];

			$sql_ary = array(
				'SELECT'	 => '*',
				'FROM'		 => array(SN_ENTRIES_TABLE => 'sn_e', ),
				'WHERE'		 => $db->sql_in_set('sn_e.user_id', $my_friends, false, true) . (($last_entry_time != 0) ? ' AND sn_e.entry_time ' . ($older ? '<' : '>') . ' ' . $last_entry_time : '') . ' AND ' . $db->sql_in_set('sn_e.entry_type', $avail_entry_types),
				'ORDER_BY'	 => 'sn_e.entry_time DESC, sn_e.entry_id DESC'
			);
			$sql = $db->sql_build_query('SELECT', $sql_ary);

			if ($older)
			{
				$rs = $db->sql_query($sql, $entries_limit + 3);
			}
			else
			{
				$rs = $db->sql_query($sql);
				$entries_limit = 9999999999;
			}

			$entries_rowset = $db->sql_fetchrowset($rs);
			$db->sql_freeresult($rs);

			$a_ap_entries = array();

			for ($i = 0; $i < $entries_limit && isset($entries_rowset[$i]); $i++)
			{
				$entries_row = $entries_rowset[$i];

				switch ($entries_row['entry_type'])
				{
				case SN_TYPE_NEW_STATUS:
					$a_ap_entries[$i] = $this->ap_load_status_entry($entries_row['entry_id'], $entries_row['entry_type'], $entries_row['user_id'], $entries_row['entry_target'], $entries_row['entry_time']);
					break;

				case SN_TYPE_NEW_FRIENDSHIP:
					$a_ap_entries[$i] = $this->ap_load_friends_entry($entries_row['entry_id'], $entries_row['entry_type'], $entries_row['user_id'], $entries_row['entry_target']);
					break;

				case SN_TYPE_PROFILE_UPDATED:
					$a_ap_entries[$i] = $this->ap_load_profile_entry($entries_row['entry_id'], $entries_row['entry_type'], $entries_row['user_id'], $entries_row['entry_target'], $entries_row['entry_additionals']);
					break;

				case SN_TYPE_NEW_FAMILY:
					$a_ap_entries[$i] = $this->ap_load_family_relation_entry($entries_row['entry_id'], $entries_row['entry_type'], $entries_row['user_id'], $entries_row['entry_target'], $entries_row['entry_additionals']);
					break;

				case SN_TYPE_NEW_RELATIONSHIP:
					$a_ap_entries[$i] = $this->ap_load_family_relation_entry($entries_row['entry_id'], $entries_row['entry_type'], $entries_row['user_id'], $entries_row['entry_target'], $entries_row['entry_additionals']);
					break;

				case SN_TYPE_EMOTE:
					$a_ap_entries[$i] = $this->ap_load_emote_entry($entries_row['entry_id'], $entries_row['entry_type'], $entries_row['user_id'], $entries_row['entry_target'], $entries_row['entry_additionals']);
					break;
				}

				$a_ap_entries[$i]['TIME'] = $entries_row['entry_time'];
			}

			return array(
				'entries'	 => $a_ap_entries,
				'more'		 => count($entries_rowset) > $entries_limit,
			);
		}

		/**
		 * Load statuses on Activity page
		 */
		function ap_load_status_entry($entry_id, $entry_type, $entry_uid, $entry_target, $entry_time)
		{
			global $db, $template, $socialnet, $user;

			if (!in_array('userstatus', $this->p_master->existing) || !method_exists($this->p_master->modules_obj['userstatus'], '_get_last_status'))
			{
				return;
			}
			else
			{
				$sn_userstatus =& $this->p_master->modules_obj['userstatus'];
			}

			$data = $sn_userstatus->_get_last_status($entry_uid, $entry_target);

			if (!isset($template->files['body']) || $template->files['body'] != 'socialnet/userstatus_status.html')
			{
				$template->set_filenames(array('body' => 'socialnet/userstatus_status.html'));
			}

			$template->destroy_block_vars('us_status');

			$comments = $this->p_master->comments->get($sn_userstatus->commentModule, 'sn-us', $entry_target, 0, 2);
			$data['COMMENTS'] = $comments['comments'];

			$template->assign_block_vars('us_status', $data);

			$template_data = $this->p_master->page_footer();
			return array(
				'ID'	 => $entry_id,
				'TYPE'	 => $entry_type,
				'DATA'	 => $template_data,
			);
		}

		/**
		 * Load FMS entries
		 */
		function ap_load_friends_entry($entry_id, $entry_type, $entry_uid, $entry_target)
		{
			global $db, $template, $phpbb_root_path, $phpEx;

			if (!isset($this->friends_entry[$entry_uid]))
			{
				$u1_sql = "SELECT user_id, username, user_colour
										FROM " . USERS_TABLE . "
											WHERE user_id = " . $entry_uid;
				$u1_result = $db->sql_query($u1_sql);
				$this->friends_entry[$entry_uid] = $db->sql_fetchrow($u1_result);
				$db->sql_freeresult($u1_result);
			}

			if (!isset($this->friends_entry[$entry_target]))
			{
				$u2_sql = "SELECT user_id, username, user_colour
										FROM " . USERS_TABLE . "
											WHERE user_id = " . $entry_target;
				$u2_result = $db->sql_query($u2_sql);
				$this->friends_entry[$entry_target] = $db->sql_fetchrow($u2_result);
				$db->sql_freeresult($u2_result);
			}

			return array(
				'ID'				 => $entry_id,
				'TYPE'				 => $entry_type,
				'USER1_USERNAME'	 => $this->friends_entry[$entry_uid]['username'],
				'USER2_USERNAME'	 => $this->friends_entry[$entry_target]['username'],
				'U_USER1_PROFILE'	 => $this->p_master->get_username_string($this->p_master->config['ap_colour_username'], 'full', $this->friends_entry[$entry_uid]['user_id'], $this->friends_entry[$entry_uid]['username'], $this->friends_entry[$entry_uid]['user_colour']),
				'U_USER2_PROFILE'	 => $this->p_master->get_username_string($this->p_master->config['ap_colour_username'], 'full', $this->friends_entry[$entry_target]['user_id'], $this->friends_entry[$entry_target]['username'], $this->friends_entry[$entry_target]['user_colour']),
			);
		}

		/**
		 * Load Profile entries
		 */
		function ap_load_profile_entry($entry_id, $entry_type, $entry_uid, $entry_target, $entry_additionals = '')
		{
			global $db, $template, $phpbb_root_path, $phpEx, $user;

			$sql = "SELECT user_id, username, user_colour
		        		FROM " . USERS_TABLE . "
									WHERE user_id = " . $entry_uid;
			$result = $db->sql_query($sql);
			$entry_user = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$entry_add = '';

			if (!empty($entry_additionals))
			{
				$entry_addArray = unserialize($entry_additionals);

				// Specific fields
				if (isset($entry_addArray['user_avatar']))
				{
					$entry_add = '';
				}
				else
				{
					// All fields
					$entry_size = sizeof($entry_addArray);
					$entry_size--;
					$idx = 0;

					foreach ($entry_addArray as $field => $value)
					{
						$field = strtoupper($field);
						$field_ = strtoupper(preg_replace('/^user_/s', '', $field));

						if (!empty($entry_add))
						{
							if ($idx < $entry_size)
							{
								$entry_add .= ', ';
							}
							else
							{
								$entry_add .= ' ' . strtolower($user->lang['AND']) . ' ';
							}
						}

						$entry_add .= '<strong>';
						$entry_add .= str_replace(' ', '&nbsp;', isset($user->lang[$field]) ? $user->lang[$field] : (isset($user->lang['SN_UP_' . $field]) ? $user->lang['SN_UP_' . $field] : (isset($user->lang[$field_]) ? $user->lang[$field_] : "{ $field }")));
						$entry_add .= '</strong>: ' . $value;

						$idx++;
					}

				}
			}

			return array(
				'ID'						 => $entry_id,
				'TYPE'						 => $entry_type,
				'USERNAME'					 => $entry_user['username'],
				'U_PROFILE'					 => $this->p_master->get_username_string($this->p_master->config['ap_colour_username'], 'full', $entry_user['user_id'], $entry_user['username'], $entry_user['user_colour']),
				'PROFILE_FIELDS'			 => $entry_add,
				'L_SN_AP_CHANGED_PROFILE'	 => $user->lang[$this->p_master->gender_lang('SN_AP_CHANGED_PROFILE', $entry_user['user_id'])],
				'L_SN_UP_CHANGED_AVATAR'	 => $user->lang[$this->p_master->gender_lang('SN_UP_CHANGED_AVATAR', $entry_user['user_id'])],
			);
		}

		/**
		 * Load Family and Relation entries
		 */
		function ap_load_family_relation_entry($entry_id, $entry_type, $entry_uid, $entry_target, $entry_additionals = '')
		{
			global $db, $template, $phpbb_root_path, $phpEx, $socialnet, $user;

			$sql = "SELECT user_id, username, user_colour
		        		FROM " . USERS_TABLE . "
									WHERE user_id = " . $entry_uid;
			$result = $db->sql_query($sql);
			$entry_user = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$partner_id = $family_id = 0;
			$partner_name = $family_name = $rel_msg = $family_msg = '';

			if ($entry_type == SN_TYPE_NEW_FAMILY)
			{
				$entry_addArray = unserialize($entry_additionals);

				if (isset($entry_addArray['family']))
				{
					if (is_numeric($entry_addArray['family']))
					{
						$family_id = $entry_addArray['family'];

						$sql = "SELECT username, user_colour
      		        		FROM " . USERS_TABLE . "
      									WHERE user_id = " . $family_id;
						$result = $db->sql_query($sql);
						$family = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);
					}
					else
					{
						$family_name = $entry_addArray['family'];
					}
				}

				$entry_status = $this->p_master->family_status($entry_target);
			}
			elseif ($entry_type == SN_TYPE_NEW_RELATIONSHIP)
			{
				if (!empty($entry_additionals))
				{
					$entry_addArray = unserialize($entry_additionals);

					if (isset($entry_addArray['relationship']))
					{
						if (is_numeric($entry_addArray['relationship']))
						{
							$partner_id = $entry_addArray['relationship'];

							$sql = "SELECT username, user_colour
        		        		FROM " . USERS_TABLE . "
        									WHERE user_id = " . $partner_id;
							$result = $db->sql_query($sql);
							$partner = $db->sql_fetchrow($result);
							$db->sql_freeresult($result);
						}
						else
						{
							$partner_name = $entry_addArray['relationship'];
						}
					}
				}

				$entry_status = $this->p_master->relationship_status($entry_target, ($partner_id != 0 || $partner_name != '') ? true : false);
			}
			else
			{
				$entry_status = '';
			}

			if (isset($partner))
			{
				$rel_msg = $this->p_master->get_username_string($this->p_master->config['ap_colour_username'], 'full', $partner_id, $partner['username'], $partner['user_colour']);
			}
			elseif ($partner_name != '')
			{
				$rel_msg = '<strong>' . $partner_name . '</strong>';
			}

			if (isset($family))
			{
				$family_msg = sprintf($user->lang[$this->p_master->gender_lang('SN_AP_ADDED_NEW_FAMILY_MEMBER', $entry_user['user_id'])], $this->p_master->get_username_string($this->p_master->config['ap_colour_username'], 'full', $family_id, $family['username'], $family['user_colour']), $entry_status);
			}
			elseif ($family_name != '')
			{
				$family_msg = sprintf($user->lang[$this->p_master->gender_lang('SN_AP_ADDED_NEW_FAMILY_MEMBER', $entry_user['user_id'])], '<strong>' . $family_name . '</strong>', $entry_status);
			}

			return array(
				'ID'								 => $entry_id,
				'TYPE'								 => $entry_type,
				'STATUS'							 => ($entry_status && $entry_type == SN_TYPE_NEW_RELATIONSHIP) ? $entry_status : '',
				'USERNAME'							 => $entry_user['username'],
				'U_PROFILE'							 => $this->p_master->get_username_string($this->p_master->config['ap_colour_username'], 'full', $entry_user['user_id'], $entry_user['username'], $entry_user['user_colour']),
				'U_PARTNER_PROFILE'					 => $rel_msg,
				'L_SN_AP_ADDED_NEW_FAMILY_MEMBER'	 => $family_msg,
				'L_SN_AP_CHANGED_RELATIONSHIP'		 => $user->lang[$this->p_master->gender_lang('SN_AP_CHANGED_RELATIONSHIP', $entry_user['user_id'])],
			);
		}

		/**
		 * Load Emotes entries
		 */
		function ap_load_emote_entry($entry_id, $entry_type, $entry_uid, $entry_target, $entry_additionals = '')
		{
			global $db, $template, $phpbb_root_path, $phpEx;

			$sql = "SELECT user_id, username, user_colour
		        		FROM " . USERS_TABLE . "
									WHERE user_id = " . $entry_uid;
			$result = $db->sql_query($sql);
			$entry_user = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$u2_sql = "SELECT user_id, username, user_colour
	        				FROM " . USERS_TABLE . "
										WHERE user_id = " . $entry_target;
			$u2_result = $db->sql_query($u2_sql);
			$user2 = $db->sql_fetchrow($u2_result);
			$db->sql_freeresult($u2_result);

			$entry_addArray = unserialize($entry_additionals);
			$emote_id = $entry_addArray['emote_id'];

			$sql = "SELECT emote_name, emote_image
		        		FROM " . SN_EMOTES_TABLE . "
									WHERE emote_id = " . $emote_id;
			$result = $db->sql_query($sql);
			$emote = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$template->assign_vars(array(
				'SN_UP_EMOTE_FOLDER' => $phpbb_root_path . SN_UP_EMOTE_FOLDER,
			));

			return array(
				'ID'				 => $entry_id,
				'TYPE'				 => $entry_type,
				'U_USER1_PROFILE'	 => $this->p_master->get_username_string($this->p_master->config['ap_colour_username'], 'full', $entry_user['user_id'], $entry_user['username'], $entry_user['user_colour']),
				'U_USER2_PROFILE'	 => $this->p_master->get_username_string($this->p_master->config['ap_colour_username'], 'full', $user2['user_id'], $user2['username'], $user2['user_colour']),
				'EMOTE_NAME'		 => $emote['emote_name'],
				'EMOTE_IMAGE'		 => ($emote['emote_image'] != '') ? $emote['emote_image'] : '',
			);
		}

		function hook_template_every_()
		{
			global $template, $phpEx, $user, $module, $config;

			// Replace register form
			if ($config['ap_replace_register'] == 1)
			{
				$script_name = str_replace('.' . $phpEx, '', $user->page['page_name']);
				if ($script_name == 'ucp')
				{
					if ($module->p_name == 'register' && $template->filename['body'] != 'ucp_agreement.html')
					{
						foreach ($template->files as $handle => $filename)
						{
							$template->files[$handle] = preg_replace('/' . $module->module->tpl_name . '\.html/si', 'socialnet/\0', $filename);
						}
						foreach ($template->filename as $handle => $file)
						{
							$template->filename[$handle] = 'socialnet/' . $file;
						}
					}
				}
			}
		}
	}
}

if (isset($socialnet) && defined('SN_AP'))
{
	if ($user->data['user_type'] == USER_IGNORE || $config['board_disable'] == 1)
	{
		$ann_data = array(
			'user_id'		 => 'ANONYMOUS',
			'more'			 => false,
			'onlineCount'	 => 0,
		);

		header('Content-type: application/json');
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		die(json_encode($ann_data));
	}

	$s_mode = request_var('mode', 'startAP');

	$socialnet->modules_obj['activitypage']->load($s_mode);
}

?>