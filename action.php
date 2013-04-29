<?php

if ( ! defined ( 'DOKU_INC' ) ) die();
if ( ! defined ( 'DOKU_PLUGIN' ) )
    define ( 'DOKU_PLUGIN', DOKU_INC.'lib/plugins/' );

require_once ( DOKU_PLUGIN.'action.php' );

class action_plugin_errno extends DokuWiki_Action_Plugin {

    function ipxe_preprocess ( &$event, $param ) {
	global $ID;

	/* Redirect error pages to error namespace */
	if ( preg_match ( '/^[0-9a-f]{8}$/', $ID ) ) {

	    /* Determine error page */
	    $page = "err:".( ( substr ( $ID, 0, 2 ) == "7f" ) ?
			     $ID : substr ( $ID, 0, 6 ) );

	    /* Redirect to error page */
	    send_redirect ( wl ( $page ) );

	    return;
	}

	/* Do nothing unless we are in the error namespace */
	if ( ! preg_match ( '/^err:([0-9a-f]{6,8})$/', $ID, $matches ) )
	    return;
	$errno = $matches[1];

	/* Redirect stale 8-character links to the new 6-character
	 * page within the error namespace.
	 */
	if ( ( strlen ( $errno ) == 8 ) &&
	     ( substr ( $errno, 0, 2 ) != "7f" ) ) {
	    $page = "err:".substr ( $errno, 0, 6 );
	    send_redirect ( wl ( $page ) );
	    return;
	}

	/* Create empty page if no page yet exists */
	if ( ! page_exists ( $ID ) ) {
	    lock ( $ID );
	    saveWikiText ( $ID, "\n", 'Autocreated' );
	    unlock ( $ID );
	    send_redirect ( wl ( $ID ) );
	}
    }

    function ipxe_errno ( &$event, $param ) {
	global $ID;
	$gitbase = "http://git.ipxe.org/ipxe.git/blob/master:/src/";

	/* Do nothing unless we are in the error namespace */
	if ( ! preg_match ( '/^err:([0-9a-f]{6,8})$/', $ID, $matches ) )
	    return;
	$errno = $matches[1];

	/* Display nothing while editing */
	if ( isset ( $_REQUEST['do'] ) && ( $_REQUEST['do'] != "show" ) )
	    return;

	/*
	  ini_set ( 'display_errors', 1 );
	  error_reporting ( E_ALL );
	*/

	/* Derive base error number (for platform-generated errors) */
	if ( substr ( $errno, 0, 2 ) == "7f" )
	    $base_errno = ( substr ( $errno, 0, 6 )."00" );

	/* Open error database */
	$dbh = new PDO ( "sqlite:".mediaFN ( "errdb:errors.db" ) );

	/* Retrieve error description */
	unset ( $description );
	$query = $dbh->query ( "SELECT description FROM errors WHERE ".
			       "errno = '".$errno."'" );
	foreach ( $query->fetchAll() as $row )
	    $description = $row['description'];

	/* If no description is available and this is a
	 * platform-generated error, try obtaining the description for
	 * the base error.
	 */
	if ( ( ! isset ( $description ) ) && isset ( $base_errno ) ) {
	    $query = $dbh->query ( "SELECT description FROM errors WHERE ".
				   "errno = '".$base_errno."'" );
	    foreach ( $query->fetchAll() as $row )
		$description = $row['description'];
	}

	/* If no description is available, use default description */
	if ( ! isset ( $description ) )
	    $description = "Unknown error";

	/* Retrieve error instances */
	$query = $dbh->query ( "SELECT DISTINCT filename, line FROM xrefs ".
			       "WHERE errno = '".$errno."' ".
			       ( isset ( $base_errno ) ?
				 "OR errno = '".$base_errno."' " : "" ).
			       "ORDER BY filename, line" );
	$instances = $query->fetchAll();

	/* Close error database */
	unset ( $dbh );

	/* Build up page header */
	$errtext = "";
	$errtext .= ( "{{ :clipart:warning.png?90x75|An error}}" );
	$errtext .= ( "====== Error: ".$description." ======\n" );
	$errtext .= ( "**(Error code ".$errno.")**\n" );
	$errtext .= ( "===== Possible sources =====\n" );
	if ( ! empty ( $instances ) ) {
	    $errtext .= ( "This error originated from one of the following ".
			  "locations within the iPXE source code:\n" );
	    foreach ( $instances as $row ) {
		$filename = $row['filename'];
		$line = $row['line'];
		$gitlink = $gitbase.$filename."#l".$line;
		$errtext .= ( "  * [[".$gitlink."|".$filename.
			      " (line ".$line.")]]\n" );
	    }
	} else {
	    $errtext .= ( "This error no longer exists in the iPXE source ".
			  "code.  You should try using the ".
			  "[[:download|latest version]] of iPXE." );
	}
	$errtext .= ( "===== General advice =====\n" );
	$errtext .= ( "  * Try using the [[:download|latest version]] of ".
		      "iPXE.  Your problem may have already been fixed.\n" );
	$errtext .= ( "  * You can [[:contact|contact]] the iPXE ".
		      "developers and other iPXE users.\n" );
	if ( ! empty ( $instances ) ) {
		$errtext .= ( "  * [[".$errno."|Refresh]] this page after 24 ".
			      "hours.  This page is actively monitored, and ".
			      "further information may be added soon.\n" );
	}
	if ( isset ( $base_errno ) && ( $base_errno != $errno ) ) {
		$errtext .= ( "===== See also =====\n" );
		$errtext .= ( "  * Error code [[:err:".$base_errno."]]\n" );
	}
	$errtext .= ( "===== Additional notes =====\n" );
	$errtext .= ( "**(Please edit this page to include any of your own ".
		      "useful hints and tips for fixing this error.)**\n" );

	/* Add error header block to page */
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
