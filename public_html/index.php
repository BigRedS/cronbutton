<?php

/* This is the 'button' bit of cronbutton. Put it on a webserver somewhere, and you can have 
 * people click buttons to enqueue jobs for the 'cron' bit to actually execute.
 * You will need to set the DB password as the CRONBUTTON_DB_PW environment variable, and if
 * you use HTTP Auth then the logged-in usernames will be logged as the 'submitter' of any jobs.
*/

// Errors to browser because this isn't for the public and I don't want to write error handling :)
ini_set('display_errors', 1);

echo "<html>";
echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">';
echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">';
echo '<div style="width:70%;margin:auto">';

// I don't see any need to keep anything other than the password a secret. It should be set as an
// environment variable; you _probably_ want `SetEnv CRONBUTTON_DB_PW <password>` in your Apache
// config.
$db_pass = $_SERVER['CRONBUTTON_DB_PW'];
$db_user = 'cronbutton_web';
$db_name = 'cronbutton';
$db_host = 'localhost';
$db_port = '3306';
$dsn = "mysql:host=$db_host;dbname=$db_name;port=$db_port;charset=utf8mb4";
$pdo = new PDO($dsn, $db_user, $db_pass);

// Tasks are possible things-to-run, we only care about a name (which is a unique identifier) and
// potentially a description which might tell someone why they would run it.
$tasks=[];
$stmt = $pdo->query('select name, description from tasks');
while($row = $stmt->fetch()){
  $tasks[ $row['name'] ] = $row['description'];
}

echo "<h1><a href=''>Cronbutton</a></h1>";
echo "<p>This is a page with some buttons on it that can trigger tasks to be run</p>";
echo "<p>You can only queue one instance of any task at a time; if there are any problems you will need to manually have a look at what's going on</p>";
// If the form has been posted then we probably have a task to run. This means we should add to the
// 'todo' table (even though things stay there once they're done). We only permit one instance of
// any given task to be planned at a time so there's an initial check here that there isn't already
// something queued:

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
  $task_name = $_POST['task'];
  $submitter = '';
  if (isset($_SERVER['PHP_AUTH_USER'])){
    $submitter = $_SERVER['PHP_AUTH_USER'];
  }

  $stmt = $pdo->query("select task_name, submitted, submitter from todo where completed is null and deleted is null and task_name = '$task_name' order by submitted desc");
  $inflight = array();
  while($row = $stmt->fetch()){
    array_push($inflight, $row);
  }
  if ($inflight and $inflight[0]){
    echo "<div class='alert alert-danger' role='alert'>Error: Job already pending for task '$task_name'; will not schedule more!</div>\n";
    echo "<p>Pending jobs for task '$task_name':";
    echo "<div style='width:50%;'><table class='table'>\n";
    echo "  <tr><th>Task Name</th><th>Submitted At</th><th>Submitted By</th></tr>\n";
    foreach ($inflight as $row){
      echo '<tr><th>'.$row['task_name'].'</td><td>'.$row['submitted'].'</td><td>'.$row['submitter'].'</td></tr>';
    }
  echo "</table></div>";
  }elseif( !$tasks[$task_name] ){
    echo "<div class='alert alert-danger' role='alert'>No task called '$task_name'; nothing to do</div>";
  }else{
    $stmt = $pdo->prepare('insert into todo (task_name, submitter) values (?, ?)');
    $stmt->execute([$task_name, $submitter]);
  }
}

// Now we list those tasks. After the form-check just so errors from that can be at the top of the
// page.
echo "<h2>Tasks</h2>";
echo "<div style='width:50%'><table class='table'>";
foreach ($tasks as $task => $description){
  echo "<tr><td>\n";
  echo "  <form method='post'><input type='hidden' name='task' value='$task'>\n";
  echo "  <input type='submit' name='submit' value='$task'>";
  echo "  </form>";
  echo "</td><td>$description</td></tr>";
}
echo "</table></div>";

// And finally a list of queued jobs; queried here so that it's after anything is inserted from a
// page request above.
$stmt = $pdo->query('select task_name, submitted, started, completed, submitter, exit_status from todo where deleted is null order by submitted desc limit 20');
$last_jobs = array();
while($row = $stmt->fetch()){
  array_push($last_jobs, $row);
}

echo '<h2>Log</h2>';
echo '<table class="table">';
echo '<tr><th>Task Name</th><th>Submitted At</th><th>Submitted By</th><th>Started At</th><th>Completed At</th><th>Exit Code</th></tr>';
foreach ($last_jobs as $job){
  echo '<tr><td>'.$job['task_name'].'</td><td>'.$job['submitted'].'</td><td>'.$job['submitter'].'</td><td>'.$job['started'].'</td><td>'.$job['completed'].'</td><td>'.$job['exit_status'].'</th></tr>';
}

echo '</div>';