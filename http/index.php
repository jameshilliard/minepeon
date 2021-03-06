<?php

require_once('inc/global.inc.php');
require_once('inc/miner.inc.php');
include_once('inc/functions.inc.php');

/*
//Moved to Cron-based PHP CLI generation
create_graph("mhsav-hour.png", "-1h", "Last Hour");
create_graph("mhsav-day.png", "-1d", "Last Day");
create_graph("mhsav-week.png", "-1w", "Last Week");
create_graph("mhsav-month.png", "-1m", "Last Month");
create_graph("mhsav-year.png", "-1y", "Last Year");

function create_graph($output, $start, $title) {
  $RRDPATH = '/mnt/config/rrd/';
  $options = array(
    "--slope-mode",
    "--start", $start,
    "--title=$title",
    "--vertical-label=Hash per second",
    "--lower=0",
    "DEF:hashrate=" . $RRDPATH . "hashrate.rrd:hashrate:AVERAGE",
    "CDEF:realspeed=hashrate,1000,*",
    "LINE2:realspeed#FF0000"
    );
  $ret = rrd_graph("/tmp/rrd/" . $output, $options);
  if (! $ret) {
    echo "<b>Graph error: </b>".rrd_error()."\n";
  }
}*/

// A few globals for the title of the page
$G_MHSav = 0;

//MinePeon temperature
if(file_exists("/var/run/mg_rate_temp")){
	$mpTemp = explode(" ", file_get_contents("/var/run/mg_rate_temp"));
}else{
	$mpTemp = array(null, null, null, null);
}

//MinePeon Version
$version = $full_model_name;

//MinePeon CPU load
$mpCPULoad = sys_getloadavg();

if (isset($_GET['url']) and isset($_GET['user'])) {

	$poolMessage = "Pool  Change Requested " . $_GET['url'] . $_GET['user'];

	//echo $poolMessage;

	promotePool($_GET['url'], $_GET['user']);

}

try{
	$stats = miner("devs", "");
	$status = $stats['STATUS'];
	$devs = $stats['DEVS'];
	$summary = miner("summary", "");
	$pools = miner("pools", "");
	$running = true;
}catch(Exception $e){
	$status = "NA";
	$devs = array();
	$summary = array(
		"SUMMARY"=>array(array(
			"BestShare"=>"NA",
			"Elapsed" => null
			)),
		"STATUS" => array(array(
			"Description" => "NA"
			)));
	$pools = array();
	$running = false;
	$error = $e->getMessage();
}

include('head.php');
include('menu.php');

require('status.php');
$proc_status = array();
$cg_status = get_status('ps_cgminer');
$proc_status['cgminer'] = ($cg_status['status']) ? 'Running' : 'Not running';
$mg_status = get_status('ps_miner_gate');
$proc_status['minergate'] = ($mg_status['status']) ? 'Running' : 'Not running';

$workmode = getMinerWorkmode(true);

$voltage = exec('cat /tmp/voltage');
$overvolt110 = file_exists("/etc/mg_ignore_110_fcc");

//Check if Spond manager is running properly (2 processes will patch as ps is launched under PHP)
function isSpondRunning() {
	return intval(file_get_contents(MINERGATE_RUNNING_FILE));
}
?>
<div class="container">
  <h3><span id="miner-header-txt"><?php echo $version;?></span></h3><br>
  <?php
  if (file_exists('/mnt/config/rrd/mhsav-hour.png')) {
  ?>
  <p class="text-center">
    <img src="rrd/mhsav-hour.png" alt="mhsav.png" />
    <img src="rrd/mhsav-day.png" alt="mhsav.png" />
</p><p class="text-center">
    <img src="rrd/mhsav-week.png" alt="mhsav.png" />
    <img src="rrd/mhsav-month.png" alt="mhsav.png" />
    <!--a href="#" id="chartToggle">Display extended charts</a-->
  </p>
  <!--p class="text-center collapse chartMore">
    <img src="rrd/mhsav-week.png" alt="mhsav.png" />
    <img src="rrd/mhsav-month.png" alt="mhsav.png" />
  </p>
  <p class="text-center collapse chartMore">
    <img src="rrd/mhsav-year.png" alt="mhsav.png" />
  </p--!>
  <?php
  } else {
  ?>
  <center><h1>Processing history</h1></center>
  <center><h2>Amazing graphs will be available shortly</h2></center>
  <?php
  }
	if(!$running){
echo "<center class='alert alert-info'><h1>".$error."</h1></center>";
	}
  ?>

<?php include($model_class.'/index_stats.php'); ?>

  <center>
    <a class="btn btn-default" href='' onclick="return send_command(<?php if(isSpondRunning())echo "'spond_stop'"; else echo "'spond_start'"?>);"><?php if(isSpondRunning())echo "Stop Miner"; else echo "Start Miner"?></a>
    <!-- a class="btn btn-default" href='/restart.php' onclick="return send_command('mining_restart', 'nice');">Restart CGMiner</a -->
    <a class="btn btn-default" href='/restart.php' onclick="return send_command('mining_restart');">Restart MinerGate</a>
    <form class="reboot-btn" method="POST" action="/reboot.php">
        <input type="submit" name="reboot" value="Reboot" class="btn btn-default">
        <input type="hidden" name="ip" value="<?php echo $_SERVER['SERVER_ADDR']; ?>" class="btn btn-default">
    </form>
    <!--<a class="btn btn-default" href='/reboot.php'>Reboot</a>-->
    <a class="btn btn-default" href='/halt.php'>ShutDown</a>
	<?php include('widgets/led_blinker.php'); ?>
    <form class="reset-graphs-btn" method="POST" action="/reset_graphs.php">
        <input type="submit" name="reset_graphs" value="Reset Graphs" class="btn btn-default">
        <input type="hidden" name="ip" value="<?php echo $_SERVER['SERVER_ADDR']; ?>" class="btn btn-default">
    </form>

  </center>
  <h3>Pools</h3>
  <table id="pools" class="table table-striped table-hover">
    <thead> 
      <tr>
        <th></th>
        <th>URL</th>
        <th>User</th>
        <th>Status</th>
        <th title="Priority">Pr</th>
        <th title="GetWorks">GW</th>
        <th title="Accept">Acc</th>
        <th title="Reject">Rej</th>
        <th title="Discard">Disc</th>
        <th title="Last Share Time">Last</th>       
        <th title="Difficulty 1 Shares">Diff1</th>        
        <th title="Difficulty Accepted">DAcc</th>
        <th title="Difficulty Rejected">DRej</th>
        <th title="Last Share Difficulty">DLast</th>
        <th title="Best Share">Best</th>      
      </tr>
    </thead>
    <tbody>
      <?php if($running) echo poolsTable($pools['POOLS']); 
	    else echo "<div class='alert alert-info'>".$error."</div>";
	?>
    </tbody>
  </table>

  <h3>Statistics</h3>
  <?php echo statsTable($devs); ?>
  <?php
  if ($debug == true) {
	
	echo "<pre>";
	print_r($pools['POOLS']);
	print_r($devs);
	echo "<pre>";
	
  }
  ?>

</div>
<!--script language="javascript" type="text/javascript">document.title = '< ?php echo $_SERVER['SERVER_ADDR']; ?>|< ?php echo $model_id; ?>';
</script-->

<?php
include('foot.php');

function statsTable($devs) {
  if(count($devs)==0){
    return "</tbody></table><div class='alert alert-info'>Miner is not ready</div>";
  }

  $devices = 0;
  $MHSav = 0;
  $MHS5m = 0;
  $Accepted = 0;
  $Rejected = 0;
  $HardwareErrors = 0;
  $Utility = 0;

  $tableRow = '<table id="stats" class="table table-striped table-hover stats">
    <thead>
      <tr>
        <th>Name</th>
<!--
        <th>ID</th>
        <th>Temp</th>
-->
        <th>GH/s</th>
        <th>5 min GH/s</th>
        <th>Accepted shares</th>
        <th>Rejected shares</th>
        <th>Errors</th>
        <th>Utility</th>
        <th>Last Share</th>
      </tr>
    </thead>
    <tbody>';

 	$hwErrorPercent = 0;
	$DeviceRejected = 0;

  foreach ($devs as $dev) {
  
	// Sort out valid deceives
	
	$validDevice = true;
 

    // Veird mismatch between us and the pool.
    if (file_exists("/var/run/mg_rate_temp")) {
        $s = file_get_contents("/var/run/mg_rate_temp");
        $s = explode(" ", $s);
        $dev['MHSav'] = intval($s[0]);
    } else {
        $s = array(0,0,0,0);
    }




	if ((time() - $dev['LastShareTime']) > 500) {
		// Only show devices that have returned a share in the past 5 minutes
        //TODO: Enable on production
		$validDevice = false;
	}

	$temperature = intval($s[1]);

	if ($validDevice) {

		if ($dev['DeviceHardware%'] >= 10 || $dev['DeviceRejected%'] > 5) {
			$tableRow = $tableRow . "<tr class=\"error\">";
		} else {
			$tableRow = $tableRow . "<tr class=\"success\">";
		}
    ?>
    <script type="text/javascript">
        document.getElementById("miner-header-txt").innerText = "<?php echo "Mining Rate: ".round($dev['MHSav']/1000,2)?>Ghs";
        document.getElementById("miner-header-txt").innerHTML = "<?php echo "Mining Rate: ".round($dev['MHSav']/1000,2)?>Ghs";
    </script>
    <?php

	$tableRow = $tableRow . "<td>" . $model_id . "</td>
      <!-- <td>" . "1" . "</td>
      <td>" . $temperature . "</td> -->
      <td>" . $dev['MHSav'] / 1000 . "</td>
      <td>" . $dev['MHS5m'] / 1000 . "</td>
      <td>" . $dev['Accepted'] . "</td>
      <td>" . $dev['Rejected'] . "</td>
      <td>" . $dev['HardwareErrors'] . "</td>
      <td>" . $dev['Utility'] . "</td>
      <td>" . date('H:i:s', $dev['LastShareTime']) . "</td>
      </tr>";

		$devices++;
		$MHSav = $MHSav + $dev['MHSav'];
		$MHS5m = $MHS5m + $dev['MHS5m'];
		$Accepted = $Accepted + $dev['Accepted'];
		$Rejected = $Rejected + $dev['Rejected'];
		$HardwareErrors = $HardwareErrors + $dev['HardwareErrors'];
		$DeviceRejected = $DeviceRejected + $dev['DeviceRejected%'];
		$hwErrorPercent = $hwErrorPercent + $dev['DeviceHardware%'];
		$Utility = $Utility + $dev['Utility'];

		$GLOBALS['G_MHSav'] = $MHSav / 1000 . " GH/s|" . $devices . " DEV";
		$GLOBALS['G_MHS5m'] = $MHS5m / 1000 . " GH/s|" . $devices . " DEV";

	}
  }


  $totalShares = $Accepted + $Rejected + $HardwareErrors;
  $tableRow = $tableRow . "
  </tbody>
  <!-- <tfoot>
  <tr>
  <th>Totals</th>
  <th>" . $devices . "</th>
  <th></th>
  <th>" . $MHSav / 1000 . "</th>
  <th>" . $MHS5m / 1000 . "</th>
  <th>" . $Accepted . "</th>
  <th>" . $Rejected . " [" . "</th>
  <th>" . $HardwareErrors . " [" . "</th>
  <th>" . $Utility . "</th>
  <th></th>
  </tr>
  </tfoot> -->
  </tbody>
  </table>
  ";

  return $tableRow;
}

function secondsToWords($seconds)
{
  $ret = "";

  /*** get the days ***/
  $days = intval(intval($seconds) / (3600*24));
  if($days> 0)
  {
    $ret .= "$days<small> day </small>";
  }

  /*** get the hours ***/
  $hours = (intval($seconds) / 3600) % 24;
  if($hours > 0)
  {
    $ret .= "$hours<small> hr </small>";
  }

  /*** get the minutes ***/
  $minutes = (intval($seconds) / 60) % 60;
  if($minutes > 0)
  {
    $ret .= "$minutes<small> min </small>";
  }

  /*** get the seconds ***/
  $seconds = intval($seconds) % 60;
  if ($seconds > 0) {
    $ret .= "$seconds<small> sec</small>";
  }

  return $ret;
}

function poolsTable($pools) {

// class="success" error warning info

  $poolID = 0;

  $table = "";
  
  array_sort_by_column($pools, 'Priority');
  
  foreach ($pools as $pool) {

    if ($pool['Status'] <> "Alive") {

      $rowclass = 'error';

    } else {

      $rowclass = 'success';

    }
	
	$poolURL = explode(":", str_replace("/", "", $pool['URL']));

    $table = $table . "
    <tr class='" . $rowclass . "'>
	<td>";
	/*if($poolID != 0) {
		$table = $table . "<a href='/?url=" . urlencode($pool['URL']) . "&user=" . urlencode($pool['User']) . "'><img src='/img/up.png'></td>";
	}*/
	$table = $table . "
    <td class='text-left'>" . $poolURL[1] . "</td>
    <td class='text-left ellipsis'>" . $pool['User'] . "</td>
    <td class='text-left'>" . $pool['Status'] . "</td>
    <td>" . $pool['Priority'] . "</td>
    <td>" . $pool['Getworks'] . "</td>
    <td>" . $pool['Accepted'] . "</td>
    <td>" . $pool['Rejected'] . "</td>
    <td>" . $pool['Discarded'] . "</td>
    <td>" . date('H:i:s', $pool['LastShareTime']) . "</td>        
    <td>" . $pool['Diff1Shares'] . "</td>       
    <td>" . round($pool['DifficultyAccepted']) . "</td>
    <td>" . round($pool['DifficultyRejected']) . "</td>
    <td>" . round($pool['LastShareDifficulty'], 0) . "</td>
    <td>" . $pool['BestShare'] . "</td>     
    </tr>";
    
    $poolID++;

  }

  return $table;

}

