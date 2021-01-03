<?php
/**
 * 2019 Dec 8 - Bootstrap
 */
/**
 * Configuration
 */
$config = array(
	'mysql' => array(
		'server' => 'localhost',
		'username' => '',
		'password' => '',
		'database' => '',
	),
	'access_password' => '', /* md5 of the actual password */
	'hostname' => '', /* domain for redirects */
	'webroot' => '', /* httpx://domain/url for redirects */
);

/* Note: Keep commented accounts present. They cannot be reused */
$accounts = array(
	'Bank' => array(
		'Checking' => '1',
		'Savings' => '2',
	),
);

/**
 * Make sure we're on the right host and protocol
 */
if ($_SERVER['HTTP_HOST'] != $config['hostname']) {
	header("Location: " . $config['webroot'], 301);
	die();
}
//if ($_SERVER['SERVER_PORT'] != '443') {
if (!array_key_exists('HTTP_HTTPS', $_SERVER)) {
	header("Location: " . $config['webroot'], 301);
	die();
}

/**
 * Check if we're submitting a password
 */
if (isset($_POST['word'])) {
	if (md5($_POST['word']) == $config['access_password']) {
		setcookie("auth", "bnk", 0);
	}
	header("Location: " . $config['webroot'], 301);
	die();
}

/**
 * Check session cookie for authentication
 */
if (!isset($_COOKIE['auth'])) {
	/* login screen */
	echo "<!DOCTYPE html><html>";
	echo "<head><meta name='viewport' content='width=device-width, initial-scale=1'></head>";
	echo "<body><center>";
	echo "Enter Password<br>";
	echo "<form method='post' action='" . $config['webroot'] . "'>";
	echo "<input name='word' type='password' value='spanked'><input type='submit' value='auth'>";
	echo "</form></center>";
	echo "</body>";
	echo "</html>";
	die();
}

/**
 * Database connection
 */
$link = mysqli_connect($config['mysql']['server'], $config['mysql']['username'], $config['mysql']['password'], $config['mysql']['database']);
if (!$link) { die("Database issues :("); }

/**
 * Make sure passed in values get set in cookies
 */

/**
 * Set Cookie to "year" to display
 */
$year = "";
if (isset($_GET['year'])) {
	setcookie("config_year", $_GET['year']);
	setcookie("config_month", '0');
	header("Location: " . $config['webroot'], 301);
	die();
}
if (!isset($_COOKIE['config_year'])) {
	setcookie("config_year", date("Y"));
	header("Location: " . $config['webroot'], 301);
	die();
}
$year = $_COOKIE['config_year'];

/**
 * Set Cookie to "month" to display
 */
$month = "";
if (isset($_GET['month'])) {
	setcookie("config_month", $_GET['month']);
	header("Location: " . $config['webroot'], 301);
	die();
}
if (!isset($_COOKIE['config_month'])) {
	setcookie("config_month", '0');
	header("Location: " . $config['webroot'], 301);
	die();
}
$month = $_COOKIE['config_month'];

/**
 * Set Cookie to "category" to display
 */
/* Displayed category */
if (isset($_GET['category'])) {
	setcookie("config_category", $_GET['category']);
	header("Location: " . $config['webroot'], 301);
	die();
}
$category = isset($_COOKIE['config_category']) ? $_COOKIE['config_category'] : "";

/**
 * Set Cookie for the account
 */
if (isset($_GET['account'])) {
	setcookie("config_account", $_GET['account']);
	header("Location: " . $config['webroot'], 301);
	die();
}
$account = isset($_COOKIE['config_account']) ? $_COOKIE['config_account'] : "0";
if ($account == "0") {
	setcookie("config_account", "1");
	header("Location: " . $config['webroot'], 301);
	die();
}

/**
 * Inline editing of an item
 */
if (isset($_GET['inline']) && isset($_GET['id']) && isset($_GET['value']))
{
	$inline = trim($_GET['inline']);
	$id = trim($_GET['id']);
	$value = trim($_GET['value']);

	$query = null;
	switch ($inline) {
		case 'dateOfRecord':
			$query = "UPDATE `tbl_account` SET `dateOfRecord`=\"" . $value . "\" WHERE `pk`=$id LIMIT 1";
			break;
		case 'item':
			// nuke ''s from $value
			$value = str_replace("'", "", $value);
			$query = "UPDATE `tbl_account` SET `item`=\"" . $value . "\" WHERE `pk`=$id LIMIT 1";
			break;
		case 'amount':
			$query = "UPDATE `tbl_account` SET `Amount`=\"" . $value . "\" WHERE `pk`=$id LIMIT 1";
			break;
		case 'category':
			$query = "UPDATE `tbl_account` SET `category`=\"" . $value . "\" WHERE `pk`=$id LIMIT 1";
			break;
	}
	if (!is_null($query))
	{
		mysqli_query($link, $query);
	}
	header('Location: ' . $config['webroot'] . '#item' . $id, 301);
	die();
}

/**
 * Delete an item
 */
if (isset($_GET['nuke'])) {
	$item = $_GET['nuke'];
	$q="DELETE FROM `tbl_account` WHERE `pk`=$item LIMIT 1";
	mysqli_query($link, $q);
	header("Location: " . $config['webroot'], 301);
	die();
}

/**
 * Flip an item to be a "recieved" item
 */
if (isset($_GET['incTotal'])) {
	$item = $_GET['incTotal'];
	$q="SELECT `incTotal` FROM `tbl_account` WHERE `pk`=$item LIMIT 1";
	$res = mysqli_query($link, $q);
	$r = mysqli_fetch_assoc($res);
	$n = "0";
	if ($r['incTotal'] == "0") {
		$n = "1";
	}
	$q = "UPDATE `tbl_account` SET `incTotal`='$n' WHERE `pk`=$item LIMIT 1";
	mysqli_query($link, $q);
	header("Location: " . $config['webroot'] . "#item" . $item, 301);
	die();
}

/**
 * Adds an item into the database
 */
if ((isset($_POST['iCategory'])) || (isset($_POST['oCategory']))) {
	$form_date = trim($_POST['iDate']); /* in HTML5 format. we need to convert this to MySQL format */
	$form_item = str_replace("'", "", trim($_POST['oItem']));
	$form_amount = trim($_POST['iAmount']);
	$form_category = trim($_POST['oCategory']); /** Option category **/
	$form_tx = trim($_POST['oType']); /** deposit or withdraw **/
	if (strlen($form_item) == 0) {
		$form_item = trim($_POST['iItem']); /** Whatever is inserted **/
	}
	if (strlen($form_category) == 0) {
		$form_category = trim($_POST['iCategory']); /** Whatever is inserted **/
	}
	$form_date = date("Y-m-d", strtotime($form_date)); /* Convert to MySQL format */
	$q = "INSERT INTO `tbl_account` (`dateOfEntry`,`dateOfRecord`,`account`,`type`,`category`,`item`,`Amount`) VALUES (NOW(), '$form_date', '" . $account . "', '$form_tx', '$form_category', \"$form_item\", '$form_amount')";
	mysqli_query($link, $q);
	header("Location: " . $config['webroot'], 301);
	die();
}

/**
 * Are we asking for a summary?
 */
$summary = isset($_GET['summary']) ? true : false;


/**
 * Spit out some HTML
 */
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<!-- Start: Bootstrap -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=0.3, shrink-to-fit=no">
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
		<!-- End: Bootstrap -->
		<title>Accounting</title>
		<script lang="javascript">
function confirmNuke(i) {
	var r = confirm("Are you sure you want to delete this record?");
	if (r) {
		document.location = './?nuke=' + i;
	}
}
function sanitize_amount() {
	var acceptable_alphabet = "1234567890.";
	var iAmount = document.getElementById('iAmount');
	var val = iAmount.value;
	var item = "";
	var revised_value = "";
	for (var x = 0; x < val.length; x++) {
		item = val.charAt(x);
		if (acceptable_alphabet.indexOf(item) != -1) {
			revised_value += item;
		}
	}
	iAmount.value = revised_value;
}
function addNew(s)
{
	if (s == 0) // btn_addnew
	{
		document.getElementById('addNewEntry').style.display='block';
		document.getElementById('btn_addnew').style.display='none';
		document.getElementById('btn_notaddnew').style.display='inline';
	}
	if (s == 1) // btn_notaddnew
	{
		document.getElementById('addNewEntry').style.display='none';
		document.getElementById('btn_addnew').style.display='inline';
		document.getElementById('btn_notaddnew').style.display='none';
	}
}
function doEdit(id, field, current_value)
{
	var val = prompt("What do you want to change?", current_value);
	if ((val != current_value) && (val.length > 0))
	{
		// we're changing something!
		document.location = '?inline=' + field + '&id=' + id + '&value=' + encodeURIComponent(val);
	}
}
		</script>
		<style>
			div { padding:0px 3px; }
			div.tal { text-align:left; }
			div.tar { text-align:right; }
			div.f { float:left; }
			div.c { clear:both; }
			div.headL { border-top:1px solid black; border-left:1px solid black; border-bottom:1px solid black; border-right:0px; }
			div.headR { border-top:1px solid black; border-left:1px solid black; border-bottom:1px solid black; border-right:1px solid black; }
			div.bodyL { border-top:0px; border-left:1px solid black; border-bottom:1px solid black; border-right:0px; }
			div.bodyR { border-top:0px; border-left:1px solid black; border-bottom:1px solid black; border-right:1px solid black; }
			/* 1  1   1   1   1   1   1   1  2   = 10 */
			/* 6  6   6   6   6   6   6   6  6   = 54 */
			/* 25 100 100 350 100 100 100 30 100 = 905 */
			/* div.allColumns { width: 1069px; } */
			div.col0 { width:25px; }
			div.col1 { width:100px; }
			div.col2 { width:100px; }
			div.col3 { width:350px; }
			div.col4 { width:120px; }
			div.col5 { width:100px; }
			div.col6 { width:100px; }
			div.col7 { width:30px; }
			div.col8 { width:100px; }
			div.iLeft { width:100px; }
			div.iRight { width:500px; }

			div.hBg { background:#dddddd; }
			div.positive {
				font-weight:bold;
			}
			div.incY {
				background-color:#00ff99;
			}
			div.incN {
				background-color:#ffffcc;
			}
			div.budget {
				background-color: #eedd82;
			}

			#addNewEntry { display:none; }

			/* Table */
			table {
				border: 1px solid rgb(200, 200, 200);
				border-collapse: collapse;
			}
			td, th {
				padding: 3px;
			}
			td {
				border: 1px solid rgb(200, 200, 200);
			}
			th {
				text-align: center;
				background: rgb(0, 222, 0);
				font-weight: bold;
			}
			td.category {
				text-align: left;
			}
			td.amount {
				text-align: right;
			}
			a.inlineEdit
			{
				text-decoration: none;
				color: rgb(0, 0, 128);
			}
		</style>
	</head>
	<body>
	<div>
		<a href="https://drive.google.com/drive/folders/1kUn6JF78uvu37sWfcuxrAxNrIJdEXRnw" target="_blank">Bank Reconciliation</a>
		&nbsp;|&nbsp;
		<a href="https://docs.google.com/spreadsheets/d/1j74I4qS36iluQ8LM6pSg0MhwtQH9YPxYibJoVQEHCzY/edit#gid=0" target="_blank">Credit Tracking</a>
	</div>
	<div>
		<button id='btn_addnew' onclick="addNew(0);">Add New</button>
		<button id='btn_notaddnew' style="display:none;" onclick="addNew(1);">Hide Add New</button>
<?php
if ($summary) {
	echo '&nbsp;<button onclick="document.location=\'./\';">Exit Summary</button>';
} else {
	echo '&nbsp;<button onclick="document.location=\'?summary=true\';">Summary</button>';
}
?>
	</div>

	<div id='addNewEntry'>
		<br />
		<div>Enter new item:</div>
		<form method="post" action="<?php echo  $config['webroot']; ?>">
			<div class="f iLeft">Date:</div><div class="f iRight"><input type="date" name="iDate" value="<?php echo date("Y-m-d"); ?>" maxlength="10" /></div><div class="c"></div>
			<div class="f iLeft">Item:</div><div class="f iRight"><select name="oItem"><option value=''></option><?php
$q="SELECT DISTINCT `item` FROM `tbl_account` WHERE YEAR(`dateOfRecord`)=YEAR(NOW()) ORDER BY `item`";
$res = mysqli_query($link, $q);
while ($r = mysqli_fetch_assoc($res)) {
	echo "<option value='" . $r['item'] . "'>" . $r['item'] . "</option>";
}
		?></select> or <input type="text" name="iItem" value="" maxlength="128" /></div><div class="c"></div>
		<div class="f iLeft">Amount:</div><div class="f iRight"><input type="tel" name="iAmount" id="iAmount" value="0.00" maxlength="12" onblur="sanitize_amount();" /> as <select name="oType"><option value="withdraw">Withdraw</option><option value="deposit">Deposit</option></select></div><div class="c"></div>
		<div class="f iLeft">Category:</div><div class="f iRight"><select name="oCategory"><option value=''></option><?php
$q="SELECT DISTINCT `category` FROM `tbl_account` ORDER BY `category`";
$res = mysqli_query($link, $q);
while ($r = mysqli_fetch_assoc($res)) {
	echo "<option value='" . $r['category'] . "'>" . $r['category'] . "</option>";
}
		?></select> or <input type="text" name="iCategory" value="" maxlength="16" /></div><div class="c"></div>
		<div class="f iLeft">&nbsp;</div><div class="f iRight"><input type="submit" value="Add it in" /></div><div class="c"></div>
	</form>
</div>

<div><hr></div>

<div><?php
/**
 * Get all the years up here for display, space separated
 */
$all_years = array();
$q = "SELECT DISTINCT YEAR(`dateOfRecord`) AS `year` FROM `tbl_account` ORDER BY YEAR(`dateOfRecord`) ASC";
$res = mysqli_query($link, $q);
while ($r = mysqli_fetch_assoc($res)) {
	$db_year = $r['year'];
	$highlight = "";
	if ($db_year == $year) {
		$highlight = 'style="background:#00ff99;"';
	}
	$all_years[] = '<button ' . $highlight . ' onclick="document.location=\'?year=' . $db_year . '\'">' . $db_year . '</button>';
}
echo "<span style='font-weight:bold;'>Year:</span> " . implode("&nbsp;", $all_years);

?></div>

<div><?php
/**
 * Get all the months up here for display, space separated
 */
$month_names = array("All", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
$all_months = array();
$q = "SELECT DISTINCT MONTH(`dateOfRecord`) AS `month` FROM `tbl_account` WHERE YEAR(`dateOfRecord`)='$year' ORDER BY MONTH(`dateOfRecord`) ASC";
$res = mysqli_query($link, $q);
while ($r = mysqli_fetch_assoc($res)) {
	$db_month = $r['month'];
	$highlight = "";
	if ($db_month == $month) {
		$highlight = 'style="background:#00ff99;"';
	}
	$all_months[] = '<button ' . $highlight . ' onclick="document.location=\'?month=' . $db_month . '\'">' . $month_names[$db_month] . '</button>';
}
// the "All" months
$highlight = "";
if ($month == "0") {
	$highlight = 'style="background:#00ff99;"';
}
$all_months[] = '<button ' . $highlight . ' onclick="document.location=\'?month=0\'">All</button>';
echo "<span style='font-weight:bold;'>Month:</span> " . implode("&nbsp;", $all_months);

?></div>

<div><hr></div>

<div><?php
/**
 * Get all the accounts up here for display, space separated
 */
// first is the account types
// type => array of accounts
foreach ($accounts as $acct_type => $accts)
{
	echo "<div>\n";
	echo "<span style='font-weight:bold;'>" . $acct_type . ":</span>\n";
	// go through accounts and spit them out
	$acct_list = array();
	foreach ($accts as $name => $value)
	{
		$highlight = "";
		if ($value == $account)
		{
			$highlight = " style='background:#00ff99;'";
		}
		$acct_list[] = '<button onclick="document.location=\'?account=' . $value . '\'"' . $highlight . '>' . $name . '</button>';
	}
	echo implode("&nbsp;", $acct_list);
	echo "</div>\n";
}
?></div>

<div><hr></div>

<?php
if ($summary) {
	echo "<div>Summary mode:</div>";

	/* Get all the categories for this year */
	$categories = array();
	$q = "SELECT DISTINCT `category` FROM `tbl_account` WHERE `account`='$account' AND YEAR(`dateOfRecord`) = '$year' ORDER BY `category` ASC";
	$res = mysqli_query($link, $q);
	while ($r = mysqli_fetch_assoc($res)) {
		$categories[] = $r['category'];
	}

	echo "<table>";

	/* Header */
	$months = array(1 => "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
	echo "<tr>";
		echo "<th>Category</th><th>YTD</th>";
		foreach ($months as $key => $value) {
			echo "<th>$value</th>";
		}
	echo "</tr>";

	/** First line, monthly totals **/
	echo "<tr>";
	echo "<td class='category'><span style='font-weight:bold;'>Total<span></td>";
	echo "<td>-</td>"; /** No need for overall year-to-date */
	foreach ($months as $key => $value) {
		$actual_total = 0;
		$q = "SELECT `type`, `Amount` FROM `tbl_account` WHERE `account`='$account' AND YEAR(`dateOfRecord`)='$year' AND MONTH(`dateOfRecord`)='$key'";
		$res = mysqli_query($link, $q);
		while ($r = mysqli_fetch_assoc($res)) {
			if ($r['type'] == 'deposit') {
				$actual_total = $actual_total + $r['Amount'];
			}
			if ($r['type'] == 'withdraw') {
				$actual_total = $actual_total - $r['Amount'];
			}
		}
		$monthColor = "";
		if (date("n") == $key) {
			$monthColor = ' style="background-color:#00ff99;" ';
		}
		echo "<td $monthColor class='amount'>" . number_format(abs($actual_total), 2) . "</td>";
	}
	echo "</tr>";

	/** Second Line, All Withdraws **/
	echo "<tr>";
	echo "<td class='category'><span style='font-weight:bold;'>Withdraws<span></td>";
	$q = "SELECT SUM(`Amount`) AS `ttl` FROM `tbl_account` WHERE `account`='$account' AND YEAR(`dateOfRecord`)='$year' AND `type`='withdraw'";
	$res = mysqli_query($link, $q);
	$r = mysqli_fetch_assoc($res);
	echo "<td>" . number_format(abs($r['ttl']), 2) . "</td>";
	foreach ($months as $key => $value) {
		$actual_total = 0;
		$q = "SELECT `type`, `Amount` FROM `tbl_account` WHERE `account`='$account' AND YEAR(`dateOfRecord`)='$year' AND MONTH(`dateOfRecord`)='$key'";
		$res = mysqli_query($link, $q);
		while ($r = mysqli_fetch_assoc($res)) {
			if ($r['type'] == 'withdraw') {
				$actual_total = $actual_total + $r['Amount'];
			}
		}
		$monthColor = "";
		if (date("n") == $key) {
			$monthColor = ' style="background-color:#00ff99;" ';
		}
		echo "<td $monthColor class='amount'>" . number_format(abs($actual_total), 2) . "</td>";
	}
	echo "</tr>";

	/** Third Line, All Deposits **/
	echo "<tr>";
	echo "<td class='category'><span style='font-weight:bold;'>Deposits<span></td>";
	$q = "SELECT SUM(`Amount`) AS `ttl` FROM `tbl_account` WHERE `account`='$account' AND YEAR(`dateOfRecord`)='$year' AND `type`='deposit'";
	$res = mysqli_query($link, $q);
	$r = mysqli_fetch_assoc($res);
	echo "<td>" . number_format(abs($r['ttl']), 2) . "</td>";
	foreach ($months as $key => $value) {
		$actual_total = 0;
		$q = "SELECT `type`, `Amount` FROM `tbl_account` WHERE `account`='$account' AND YEAR(`dateOfRecord`)='$year' AND MONTH(`dateOfRecord`)='$key'";
		$res = mysqli_query($link, $q);
		while ($r = mysqli_fetch_assoc($res)) {
			if ($r['type'] == 'deposit') {
				$actual_total = $actual_total + $r['Amount'];
			}
		}
		$monthColor = "";
		if (date("n") == $key) {
			$monthColor = ' style="background-color:#00ff99;" ';
		}
		echo "<td $monthColor class='amount'>" . number_format(abs($actual_total), 2) . "</td>";
	}
	echo "</tr>";

	/* Go through each category */
	foreach ($categories as $cat) {
		echo "<tr>";
		echo "<td class='category'>$cat</td>";

		/** Year to date */
		$actual_total = 0;
		$q = "SELECT `type`, `Amount` FROM `tbl_account` WHERE `account`='$account' AND YEAR(`dateOfRecord`)='$year' AND `category`='$cat'";
		$res = mysqli_query($link, $q);
		while ($r = mysqli_fetch_assoc($res)) {
			if ($r['type'] == 'deposit') {
				$actual_total = $actual_total + $r['Amount'];
			}
			if ($r['type'] == 'withdraw') {
				$actual_total = $actual_total - $r['Amount'];
			}
		}
		$monthColor = "";
		if (date("n") == $key) {
			$monthColor = ' style="background-color:#00ff99;" ';
		}
		echo "<td $monthColor class='amount'>" . number_format(abs($actual_total), 2) . "</td>";

		foreach ($months as $key => $value) {
			$actual_total = 0;
			$q = "SELECT `type`, `Amount` FROM `tbl_account` WHERE `account`='$account' AND YEAR(`dateOfRecord`)='$year' AND MONTH(`dateOfRecord`)='$key' AND `category`='$cat'";
			$res = mysqli_query($link, $q);
			while ($r = mysqli_fetch_assoc($res)) {
				if ($r['type'] == 'deposit') {
					$actual_total = $actual_total + $r['Amount'];
				}
				if ($r['type'] == 'withdraw') {
					$actual_total = $actual_total - $r['Amount'];
				}
			}
			$monthColor = "";
			if (date("n") == $key) {
				$monthColor = ' style="background-color:#00ff99;" ';
			}
			echo "<td $monthColor class='amount'>" . number_format(abs($actual_total), 2) . "</td>";
		}

		echo "</tr>";
	}
	echo "</table>";

} else {
	/**
	 * User Interface for adding/viewing
	 */
	if (strlen($category) > 0) {
		echo "<div>Category: <span style='font-weight:bold;'>$category</span> | <a href='?category'>Clear</a></div>";
	}
?>

<div class="allColumns">
	<div class="col0 tal headL hBg f">&nbsp;</div>
	<div class="col1 tal headL hBg f">Entry</div>
	<div class="col2 tal headL hBg f">Date</div>
	<div class="col3 tal headL hBg f">Item</div>
	<div class="col4 tal headL hBg f">Category</div>
	<div class="col5 tar headL hBg f">Amount</div>
	<div class="col8 tar headL hBg f">Total</div>
	<div class="col6 tar headL hBg f">Inc Total</div>
	<div class="col7 tar headR hBg f">Inc</div>
	<div class="c"></div>

<?php
$rt = 0; /** Running total **/
$ot = 0; /** Overall Total **/
$pct = ""; /* precached text */

$sq=""; /** Subquery **/
$sq = " WHERE `account`='$account' ";
if (strlen($category) > 0) {
	$sq = $sq . " AND `category`='" . urldecode($category) . "' ";
}
$sq = $sq . " AND YEAR(`dateOfRecord`)='$year' ";
if ($month != "0")
{
	$sq = $sq . " AND MONTH(`dateOfRecord`)='$month' ";
}

$dateOfRecord = "";
$q = "SELECT * FROM `tbl_account` $sq ORDER BY `dateOfRecord` ASC, `type` ASC, `Amount` DESC";
$res = mysqli_query($link, $q);
while ($r = mysqli_fetch_assoc($res)) {
	/** Adding 19 Sep 2016 'b_*' categories for budgeting **/
	$inBudget = false;
	if (strtolower(substr($r['category'], 0, 2)) == "b_") {
		$inBudget = true;
	}

	$incTotal = "N";
	$incBG = "incN";
	if ($r['incTotal'] == "1") {
		if ($r['type'] == 'deposit') {
			if (!$inBudget) {
				$rt = $rt + $r['Amount'];
			} else { /* backwards for budgeting */
				$rt = $rt - $r['Amount'];
			}
		}
		if ($r['type'] == 'withdraw') {
			if (!$inBudget) {
				$rt = $rt - $r['Amount'];
			} else { /* backwards for budgeting */
				$rt = $rt + $r['Amount'];
			}
		}
		$incTotal = "Y";
		$incBG = "incY";
	}
	if ($r['type'] == 'deposit') {
		if (!$inBudget) {
			$ot = $ot + $r['Amount'];
		} else { /* backwards for budgeting */
			$ot = $ot - $r['Amount'];
		}
	}
	if ($r['type'] == 'withdraw') {
		if (!$inBudget) {
			$ot = $ot - $r['Amount'];
		} else { /* backwards for budgeting */
			$ot = $ot + $r['Amount'];
		}
	}

	$pk = $r['pk'];

	$t = '';
	$t .= '<a name="item' . $pk . '"></a>';
	$t .= '<div class="col0 tal bodyL ' . $incBG . ' f"><a href="#" onclick="confirmNuke(\'' . $pk . '\');">X</a></div>';
	$t .= "<div class='col1 tal bodyL $incBG f'>" . $r['dateOfEntry'] . "</div>";
	$t .= '<div class="col2 tal bodyL ' . $incBG . ' f"><a title="Click to Edit" class="inlineEdit" onclick=\'doEdit("' . $pk . '","dateOfRecord","' . $r['dateOfRecord'] . '");\'>' . $r['dateOfRecord'] . '</a></div>';
	$item = $r['item'];
	if (strlen($item) == 0)
	{
		$item = '-';
	}
	$t .= '<div class="col3 tal bodyL ' . $incBG . ' f"><a title="Click to Edit" class="inlineEdit" onclick=\'doEdit("' . $pk . '","item","' . $item . '");\'>' . $item . '</a></div>';
	$b = $inBudget ? 'budget' : '';
	$t .= "<div class='col4 tal bodyL $incBG f $b'><a title='Sort by this category' href='?category=" . urlencode($r['category']) . "'>" . $r['category'] . "</a>&nbsp;<a title='click here to change category' href='javascript:doEdit(\"" . $pk . "\", \"category\", \"" . $r['category'] . "\");'>-</a></div>";
	$p = ($r['type'] == 'deposit') ? 'positive' : '';
	$amount = number_format($r['Amount'], 2, ".", "");
	$t .= '<div class="col5 tar bodyL ' . $incBG . ' f ' . $p . '"><a title="Click to Edit" class="inlineEdit" onclick=\'doEdit("' . $pk . '","amount","' . $amount . '");\'>' . $amount . '</a></div>';
	$t .= "<div class='col8 tar bodyL $incBG f'>" . number_format($ot, 2, ".", "") . "</div>";
	$t .= "<div class='col6 tar bodyL $incBG f'>" . number_format($rt, 2, ".", "") . "</div>";
	$t .= "<div class='col7 tar bodyR $incBG f'><a href='?incTotal=" . $pk . "'>$incTotal</a></div>";
	$t .= "<div class='c'></div>\n";

	/**
	* Determine if date of record has changed. Place a small spacer between months
	*/
	$currentDateOfRecord = substr($r['dateOfRecord'], 0, 7);
	if ($dateOfRecord == "") {
		$dateOfRecord = $currentDateOfRecord;
	} else {
		if ($dateOfRecord != $currentDateOfRecord) {
			$dateOfRecord = $currentDateOfRecord;
			$t .= "<div style='padding-top:4px;'></div>";
		}
	}

	$pct = $t . $pct;
}

/**
 * Final output
 */
echo $pct;
?>
</div>
</div>
<?php
} /* End of 'if summary = true' else */
?>
		<!-- Start: Bootstrap -->
		<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
		<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
		<!-- End: Bootstrap -->
	</body>
</html><?php
mysqli_close($link);
