<?php

if ( ! defined ( 'DOKU_INC' ) ) die();
if ( ! defined ( 'DOKU_PLUGIN' ) )
    define ( 'DOKU_PLUGIN', DOKU_INC.'lib/plugins/' );

require_once ( DOKU_PLUGIN.'action.php' );

class action_plugin_errno extends DokuWiki_Action_Plugin {

    function ipxe_preprocess ( &$event, $param ) {
	global $ID;

	/* Do nothing on non-error pages */
	if ( ! preg_match ( '/^[0-9a-f]{8}$/', $ID ) )
	    return;
	$errno = $ID;

	/* Create empty page if no page yet exists */
	if ( ! page_exists ( $ID ) ) {
	    lock ( $ID );
	    saveWikiText ( $ID, "\n", 'Autocreated' );
	    unlock ( $ID );
	    send_redirect ( wl ( $ID, array ( "do" => "show" ), false, "&" ) );
	}
    }

    function ipxe_errno ( &$event, $param ) {
	global $ID;
	$errtext = "";
	$gitbase = "http://git.ipxe.org/ipxe.git/blob/master:/src/";

	/* Do nothing on non-error pages */
	if ( ! preg_match ( '/^[0-9a-f]{8}$/', $ID ) )
	    return;
	$errno = $ID;

	/* Display nothing while editing */
	if ( ( $_REQUEST['do'] == 'edit' ) || ( $_POST['do']['preview'] ) )
	    return;

	/*
	  ini_set ( 'display_errors', 1 );
	  error_reporting ( E_ALL );
	*/

	/* Open error database */
	$dbh = new PDO ( "sqlite:".mediaFN ( "errdb:errors.db" ) );

	/* Retrieve error description */
	$description = "Unknown error";
	$query = $dbh->query ( "SELECT description FROM errors WHERE ".
			       "errno = '".$errno."'" );
	foreach ( $query->fetchAll() as $row )
	    $description = $row['description'];
	$errtext .= "{{ :clipart:warning.png?90x75|An error}}";
	$errtext .= "====== Error: ".$description." ======\n";
	$errtext .= "**(Error number 0x".$errno.")**\n";

	/* Retrieve error instances */
	$errtext .= "===== Possible sources =====\n";
	$errtext .= ( "This error originated from one of the following ".
		      "locations within the iPXE source code:\n" );
	$query = $dbh->query ( "SELECT filename, line FROM xrefs WHERE ".
			       "errno = '".$errno."'" );
	foreach ( $query->fetchAll() as $row ) {
	    $filename = $row['filename'];
	    $line = $row['line'];
	    $gitlink = $gitbase.$filename."#l".$line;
	    $errtext .= "  * [[".$gitlink."|".$filename." (line ".$line.")]]\n";
	}

	/* Close error database */
	unset ( $dbh );

	/* Add error header block to page */
	$errtext .= "===== Additional notes =====\n";
	$errtext .= ( "**(Please edit this page to include any of your own ".
		      "useful hints and tips for fixing this error.)**\n" );
	$event->data = ( p_render ( 'xhtml', p_get_instructions ( $errtext ),
				    $info ) ).$event->data;
    }

    function getInfo() {
	return array (
		      'name' => 'iPXE error plugin',
		      'email' => 'mcb30@ipxe.org',
		      'date' => '19/10/2010',
		      'author' => 'Michael Brown',
		      'desc' => 'Adds information to iPXE error pages',
		      'url' => ''
		      );
    }

    function register ( &$controller ) {
	$controller->register_hook ( 'ACTION_ACT_PREPROCESS', 'BEFORE',
				     $this, 'ipxe_preprocess', array() );
	$controller->register_hook ( 'TPL_CONTENT_DISPLAY', 'BEFORE',
				     $this, 'ipxe_errno', array() );
    }
}

?>