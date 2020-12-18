<?php
// connect to DB
$dbConnection =  new mysqli(getenv('DBHOST'), getenv('DBUSR'), getenv('DBPASS'), getenv('DBSCHEMA'), getenv('DBPORT'));
// if we cant connect, respond with server error
if ($dbConnection->connect_error) {
	respondWith("HTTP/1.1 503 Site Unavailable", 'Site temporarily unavailable.');
	exit;
}

// check to make sure we have origin set
$myDbResults = $dbConnection->query("select origin from cache.info limit 0, 1");
if(! $myOrigin = $myDbResults->fetch_assoc()) {
	// if not, we ask to set it
	require_once(__DIR__ . '/setOrigin.php');
	exit;
}

// use the URL as the key (ie. path/to/my/page )
//   we have configured our .htaccess file to pass the path via query string
if(! $myKey = htmlspecialchars($_GET["q"]) ){
	// if we didnt have a path, we assume the homepage
	$myKey = '/';
}

//
	if($_SERVER['REQUEST_METHOD'] === 'POST') {
		($dbConnection->query("delete from cache.dataStore where `key` = '{$myKey}'")) ?error_log("Succesfully Deleted Record")
		:error_log ("Error" . $dbConnection->error);
	}
	
// look-up in cache data store
$myDbResults = $dbConnection->query("select * from cache.dataStore where `key` = '$myKey'");

// if we have data, 
if($cacheRecord = $myDbResults->fetch_assoc()) {
	// send response
	respondWith($cacheRecord['header'],  addRegButton($cacheRecord['html'], $adminPg));

	// check expiry 
	$now = date('YmdHis');
	if($now > $cacheRecord['expiry']) {
		// if expired, add to queue
		$dbConnection->query("insert into cache.queue (payload) values ('$myKey')");
	}

// if we did not have data, serve a 404 (with refresh header) and queue page to get generated
} else {
	// send response
	respondWith("HTTP/1.1 404 Not Found\nRefresh: 5;\nContent-Type: text/html; charset=UTF-8", "Generating cache for <b>$myKey</b></br></br>Page will automatically refresh");
	// add to queue
	$dbConnection->query("insert into cache.queue (payload) values ('$myKey')");
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// helper functions
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //

function respondWith($header, $payload) {
	// break up header into lines
	$headerLines = explode("\n",  $header);
	// send each header line
	foreach($headerLines as $h) {
		header($h);
	}
	// send data
	echo $payload;
	return;
}



/* 
For Pages with the query String admin=True, I have add a button at the bottom Left hand corner that will regenerate the pages' 
cache and then refresh the page.
//The idea is to use Regex to replace a string where all all matches are found with the replacement pattern
The css properties are designed to maintain the DOM order.
*/
$adminPg = htmlspecialchars($_GET["admin"]); 
//Created a button that makes a Post request to refresh the current page.
function addRegButton($myHtml, $adminPg) {
	if($adminPg === false) {
		return $myHtml;
	}
		
	$str1 = '/<head>/';
	$str2 = '/<\/body>/';
	$pattern1 = <<<EOT
	<head>
	<style>
	button.refreshBtn{
		position: fixed;
		bottom: 0;
		background: #4CAF50; 
		padding: 5px;
	}</style>
	EOT;

	$pattern2 = <<<EOT
		<form action="" method="POST">
		<button class="refreshBtn">Refresh</button>
		</form>
		</body>
	EOT;
	$myHtml = preg_replace($str1, $pattern1, $myHtml);
	$myHtml = preg_replace($str2, $pattern2, $myHtml);
    return $myHtml;
}
