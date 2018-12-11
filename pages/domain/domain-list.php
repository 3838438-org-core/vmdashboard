<?php
// If the SESSION has not been started, start it now
if (!isset($_SESSION)) {
    session_start();
}

// If there is no username, then we need to send them to the login
if (!isset($_SESSION['username'])){
  $_SESSION['return_location'] = $_SERVER['PHP_SELF']; //sets the return location used on login page
  header('Location: ../login.php');
}

// We are now going to grab any GET/POST data and put in in SESSION data, then clear it.
// This will prevent duplicatig actions when page is reloaded.
if (isset($_GET['action'])) {
    $_SESSION['uuid'] = $_GET['uuid'];
    $_SESSION['action'] = $_GET['action'];
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

//if theme is dark change sidebar data-color
if (isset($_SESSION[themeColor])){
  $themeColor = $_SESSION['themeColor'];
} else {
  $themeColor = "white";
}

require('../header.php');
require('../navbar.php');

$uuid = $_SESSION['uuid']; //grab the $uuid variable from $_GET, only used for actions below
$action = $_SESSION['action']; //grab the $action variable from $_SESSION
unset($_SESSION['action']); //Unset the Action Variable to prevent repeats of action on page reload
$domName = $lv->domain_get_name_by_uuid($uuid); //get the name of virtual machine with $uuid is present
$dom = $lv->get_domain_object($domName); //gets the resource id for a domain

//This will turn a shutdown virtual machine on. This option in only given when a machine is shutdown
if ($action == 'domain-start') {
  $notification = $lv->domain_start($domName) ? "" : 'Error while starting domain: '.$lv->get_last_error();
}

//This will pause a virtual machine and temporaily save it's state
if ($action == 'domain-pause') {
  $notification = $lv->domain_suspend($domName) ? "" : 'Error while pausing domain: '.$lv->get_last_error();
}

//This will resume a paused virtual machine. Option is given only if a machine is paused
if ($action == 'domain-resume') {
  $notification = $lv->domain_resume($domName) ? "" : 'Error while resuming domain: '.$lv->get_last_error();
}

//This is used to gracefully shutdown the guest.
//There are many reasons why a guest cannot gracefully shutdown so if it can't, let the user know that
if ($action == 'domain-stop') {
  $notification = $lv->domain_shutdown($domName) ? "" : 'Error while stopping domain: '.$lv->get_last_error();
}

//This will forcefully shutdown the virtual machine guest
if ($action == 'domain-destroy') {
  $notification = $lv->domain_destroy($domName) ? "" : 'Error while destroying domain: '.$lv->get_last_error();
}

?>

<div class="content">

  <div class="card card-stats-left">
    <div class="card-header card-header-warning card-header-icon">
      <div class="card-icon">
        <i class="material-icons">list</i>
      </div>
      <h3 class="card-title">Virtual Machine List</h3>
      <p class="card-category">Select guest for more options</p>
    </div>
    <div class="card-body">

      <a href="domain-create.php"><i class="fa fa-plus"></i> Create Virtual Machine </a>  &nbsp; &nbsp; &nbsp; &nbsp;
      <a href="domain-create-xml.php"><i class="fa fa-plus"></i> Define VM from XML</a> 
      <br /> <br />

      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="text-none">
            <th>Name</th>
            <th>CPUs</th>
            <th>Memory</th>
            <th>Memory Usage</th>
            <th>Disks</th>
            <th>State</th>
            <th>Actions</th>
          </thead>
          <tbody>
            <?php
            $doms = $lv->get_domains();
            foreach ($doms as $name) {
              $dom = $lv->get_domain_object($name);
              $uuid = libvirt_domain_get_uuid_string($dom);
              $active = $lv->domain_is_active($dom);
              $info = $lv->domain_get_info($dom);
              $mem = number_format($info['memory'] / 1024, 0, '.', '').' MB';
              $mem_stats = $lv->domain_get_memory_stats($name);

              $mem_used = (1- $mem_stats[4]/$mem_stats[5])*100;
              if ($mem_stats != false && !isset($mem_stats[4]) && !isset($mem_stats[5])){
                $mem_used = 100;
              }
              $cpu = $info['nrVirtCpu'];

              $state = $lv->domain_state_translate($info['state']);
              $id = $lv->domain_get_id($dom);
              $arch = $lv->domain_get_arch($dom);
              $vnc = $lv->domain_get_vnc_port($dom);
              $nics = $lv->get_network_cards($dom);

              if (($diskcnt = $lv->get_disk_count($dom)) > 0) {
                $disks = $diskcnt.' / '.$lv->get_disk_capacity($dom);
                $diskdesc = 'Current physical size: '.$lv->get_disk_capacity($dom, true);
              } else {
                $disks = '-';
                $diskdesc = '';
              }

              unset($tmp);
              unset($dom);

              echo "<tr>" .
                "<td> <a href=\"domain-single.php?uuid=$uuid\">" . htmlentities($name) .  "</a> </td>" .
                "<td> $cpu </td>" .
                "<td> $mem </td>" .
                "<td>
                  <div class=\"progress\">
                  <div class=\"progress-bar progress-bar-danger\" role=\"progressbar\" style=\"width: $mem_used%\" aria-valuenow=\"$mem_used\" aria-valuemin=\"0\" aria-valuemax=\"100\"></div>
                  </div>
                </td>" .
                "<td title='$diskdesc'>$disks</td>" .
                "<td>$state</td>" .
                "<td>";

              if ($lv->domain_is_running($name)){
               echo "<a href=\"?action=domain-stop&amp;uuid=$uuid\">Shutdown</a> | <a href=\"?action=domain-destroy&amp;uuid=$uuid\">Power off</a> | <a href=\"?action=domain-pause&amp;uuid=$uuid\">Pause</a>";
              } else if ($lv->domain_is_paused($name)){
               echo "<a href=\"?action=domain-resume&amp;uuid=$uuid\">Resume</a>";
              } else {
               echo "<a href=\"?action=domain-start&amp;uuid=$uuid\"><i class=\"fa fa-power-off\"></i> Power on</a>";
              }
              echo "</td>";
              echo "</tr>";
            }
            ?>
          </tbody>
        </table>
      </div> <!-- End table -->
    </div> <!-- End Card Body -->
  </div> <!-- End Card -->
</div> <!--End Content -->

<script>
window.onload =  function() {
  <?php
  if ($notification) {
    echo "showNotification(\"top\",\"right\",\"$notification\");";
  }
  ?>
}

function showNotification(from, align, text){
    color = 'warning';
    $.notify({
        icon: "",
        message: text
      },{
          type: color,
          timer: 500,
          placement: {
              from: from,
              align: align
          }
      });
}
</script>

<?php
require('../footer.php');
?>
