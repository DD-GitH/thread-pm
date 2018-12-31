<?php

/*
- It a tools member can post Key software/card private, only once person can see it when click "see" button!
- It connecting with Point or Thanks System module, if user "view" "Key/Card" system will recharge "Thanked" or "Point" of member
- Member can post multiple Key/Card, with a Key/Card is one line
http://prntscr.com/amj0pt http://prntscr.com/amj1ef http://prntscr.com/amj2iq http://prntscr.com/amj4p0 http://prntscr.com/amj5ll http://prntscr.com/amj72i
*/

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook('newthread_do_newthread_end', 'threadpm_append');
$plugins->add_hook('newreply_do_newreply_end', 'threadpm_send');

function threadpm_info()
{
    return array(
        "name"          => "Thread PM",
        "description"   => "User receives specific PM when replies to specific thread",
        "website"       => "https://www.developement.design",
        "author"        => "Amazigh OUZRIAT",
        "authorsite"    => "https://community.mybb.com/user-76958.html",
        "version"       => "1.0",
        "guid"          => "",
        "codename"      => "threadpm",
        "compatibility" => "*"
    );
}

function threadpm_install()
{
    global $db;
    $db->query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `threadpm` TEXT NULL");
}

function threadpm_uninstall()
{
    global $db;
    $db->query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `threadpm`");
}

function threadpm_is_installed()
{
    global $db;
    $table = TABLE_PREFIX.'threads';
    $column = 'threadpm';
    $query = "SHOW COLUMNS FROM $table LIKE '$column'";
    $result = $db->query($query) or die(mysql_error());
    if($num_rows = $db->num_rows($result) > 0) 
    {
	   return true;
    } 
    else 
    {
	   return false;
    }
}

function threadpm_activate()
{
    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets('newthread', '#'.preg_quote('{$modoptions}').'#i', '<tr>
<td class="trow1" valign="top"><strong>Private message content</strong></td>
<td class="trow1"><textarea name="threadpm" id="threadpm" rows="5" cols="80"></textarea></td>
</tr>
{$modoptions}'); 
}

function threadpm_deactivate()
{
    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets('newthread', '#'.preg_quote('<tr>
<td class="trow1" valign="top"><strong>Private message content</strong></td>
<td class="trow1"><textarea name="threadpm" id="threadpm" rows="5" cols="80"></textarea></td>
</tr>').'#i', '');
}

function threadpm_append()
{
    global $db, $mybb, $tid;
    if (isset($mybb->input["threadpm"]))
    {
        if (empty($mybb->input["threadpm"]))
        {
            $mybb->input["threadpm"] = NULL;
        }
        $update_array = array(
         "threadpm" => $db->escape_string($mybb->input["threadpm"]),
        );
        $db->update_query("threads", $update_array, "tid = '".$tid."'"); 
    }
} 

function threadpm_send()
{
    global $mybb, $db, $thread;
    $n = 0;
    $query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid = ".$thread['tid']." and uid = ".$mybb->user['uid']);
    $query = $db->simple_select("posts", "*", "tid=".$thread['tid']." and uid=".$mybb->user['uid']);
    while ($result = $db->fetch_array($query))
    {
        $n = $n+1;
        if ($result['edittime'] != 0)
        {
            $n = $n+1;
        }
    }
    if ($mybb->user['uid'] != $thread['uid'] and $n == 1)
    {
        require_once MYBB_ROOT."inc/datahandlers/pm.php";
        $pmhandler = new PMDataHandler();

        $pm = array(
            "subject" => $db->escape_string($thread['subject']),
            "message" => $db->escape_string($thread['threadpm']),
            "uid" => intval($thread['uid']),
            "fromid" => intval($thread['uid']),
            "toid" => intval($mybb->user['uid'])
        );
        
        $pmhandler->set_data($pm);
        if(!$pmhandler->validate_pm())
	    {
           $pm_errors = $pmhandler->get_friendly_errors();
		   $send_errors = inline_error($pm_errors);
           exit;
        }
        else
        {
            $pminfo = $pmhandler->insert_pm();
        }
        //$query = $db->query("INSERT INTO `mybb_privatemessages` (`pmid`, `uid`, `toid`, `fromid`, `recipients`, `folder`, `subject`, `icon`, `message`, `dateline`, `deletetime`, `status`, `statustime`, `includesig`, `smilieoff`, `receipt`, `readtime`, `ipaddress`) VALUES (NULL, '".$mybb->user['uid']."', '".$mybb->user['uid']."', '".$thread['uid']."', 'a:1:{s:2:\"to\";a:1:{i:0;s:1:\"".$mybb->user['uid']."\";}}', '1', 'Re : ".trim($thread['subject'])."', '0', '".trim($thread['threadpm'])."', UNIX_TIMESTAMP(), '0', '0', '0', '0', '0', '0', '0', '');");
        
        $update_array = array(
         "totalpms" => $mybb->user['totalpms'] + 1,
         "unreadpms" => $mybb->user['unreadpms'] + 1,
         "pmnotice" => 2
        );
        $db->update_query("users", $update_array, "uid = '".$mybb->user['uid']."'"); 
    }
}