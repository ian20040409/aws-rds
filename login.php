<?php include "../inc/dbinfo.inc"; ?>
<head>
<meta charset="utf-8" />
  <link rel="stylesheet" href="/photo_post/st/css/main.css" />
							<link rel="icon" href="images/Sports_Mode_icon-icons.com_54137.ico">
							<noscript><link rel="stylesheet" href="/photo_post/st/css/noscript.css" /></noscript>
							
		
		<noscript><link rel="stylesheet" href="photo_post/assets/css/noscript.css" /></noscript>
</head>
<html>
<body>

<?php

  session_start();

  /* Connect to MySQL and select the database. */
  $connection = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

  if (mysqli_connect_errno()) echo "Failed to connect to MySQL: " . mysqli_connect_error();

  $database = mysqli_select_db($connection, DB_DATABASE);

  /* Ensure that the USERS and EMPLOYEES tables exist. */
  VerifyUsersTable($connection, DB_DATABASE);
  VerifyEmployeesTable($connection, DB_DATABASE);

  /* User Registration and Login */
  if (isset($_POST['action']) && $_POST['action'] == 'register') {
    $username = htmlentities($_POST['username']);
    $password = password_hash(htmlentities($_POST['password']), PASSWORD_DEFAULT);
    RegisterUser($connection, $username, $password);
  }

  if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $username = htmlentities($_POST['username']);
    $password = htmlentities($_POST['password']);
    if (LoginUser($connection, $username, $password)) {
      $_SESSION['username'] = $username;
    } else {
      echo "<p>Invalid username or password.</p>";
    }
  }

  /* Logout */
  if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
  }

  /* If user is logged in, show employee form */
  if (isset($_SESSION['username'])) {
    echo "<p>Welcome, " . $_SESSION['username'] . "! (<a href='?action=logout'>Logout</a>)</p>";

    /* If input fields are populated, add a row to the EMPLOYEES table. */
    $employee_name = htmlentities($_POST['NAME'] ?? '');
    $employee_address = htmlentities($_POST['ADDRESS'] ?? '');

    if (strlen($employee_name) || strlen($employee_address)) {
      AddEmployee($connection, $employee_name, $employee_address);
    }

    ?>

    <!-- Input form -->
    <form action="<?PHP echo $_SERVER['SCRIPT_NAME'] ?>" method="POST">
      <table border="0">
        <tr>
          <td>NAME</td>
          <td>ADDRESS</td>
        </tr>
        <tr>
          <td>
            <input type="text" name="NAME" maxlength="45" size="30" />
          </td>
          <td>
            <input type="text" name="ADDRESS" maxlength="90" size="60" />
          </td>
          <td>
            <input type="submit" value="Add Data" />
          </td>
        </tr>
      </table>
    </form>

    <!-- Display table data. -->
    <table border="1" cellpadding="2" cellspacing="2">
      <tr>
        <td>ID</td>
        <td>NAME</td>
        <td>ADDRESS</td>
      </tr>

    <?php

    $result = mysqli_query($connection, "SELECT * FROM EMPLOYEES");

    while($query_data = mysqli_fetch_row($result)) {
      echo "<tr>";
      echo "<td>",$query_data[0], "</td>",
           "<td>",$query_data[1], "</td>",
           "<td>",$query_data[2], "</td>";
      echo "</tr>";
    }
    ?>

    </table>

    <?php
    /* Clean up. */
    mysqli_free_result($result);
  } else {
    ?>

    <h2>Login</h2>
    <form action="<?PHP echo $_SERVER['SCRIPT_NAME'] ?>" method="POST">
      <input type="hidden" name="action" value="login" />
      Username: <input type="text" name="username" /><br />
      Password: <input type="password" name="password" /><br />
      <input type="submit" value="Login" />
    </form>

    <a href="register.php">Register</a>
    

    <?php
  }

  mysqli_close($connection);

  /* Register a user */
  function RegisterUser($connection, $username, $password) {
    $u = mysqli_real_escape_string($connection, $username);
    $p = mysqli_real_escape_string($connection, $password);

    $query = "INSERT INTO USERS (USERNAME, PASSWORD) VALUES ('$u', '$p');";

    if(!mysqli_query($connection, $query)) echo("<p>Error registering user.</p>");
  }

  /* Login a user */
  function LoginUser($connection, $username, $password) {
    $u = mysqli_real_escape_string($connection, $username);
    $query = "SELECT PASSWORD FROM USERS WHERE USERNAME = '$u'";
    $result = mysqli_query($connection, $query);

    if ($result && mysqli_num_rows($result) > 0) {
      $row = mysqli_fetch_assoc($result);
      return password_verify($password, $row['PASSWORD']);
    }
    return false;
  }

  /* Add an employee to the table. */
  function AddEmployee($connection, $name, $address) {
     $n = mysqli_real_escape_string($connection, $name);
     $a = mysqli_real_escape_string($connection, $address);

     $query = "INSERT INTO EMPLOYEES (NAME, ADDRESS) VALUES ('$n', '$a');";

     if(!mysqli_query($connection, $query)) echo("<p>Error adding employee data.</p>");
  }

  /* Check whether the USERS table exists and, if not, create it. */
  function VerifyUsersTable($connection, $dbName) {
    if(!TableExists("USERS", $connection, $dbName))
    {
       $query = "CREATE TABLE USERS (
           ID int(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
           USERNAME VARCHAR(45) UNIQUE,
           PASSWORD VARCHAR(255)
         )";

       if(!mysqli_query($connection, $query)) echo("<p>Error creating USERS table.</p>");
    }
  }

  /* Check whether the EMPLOYEES table exists and, if not, create it. */
  function VerifyEmployeesTable($connection, $dbName) {
    if(!TableExists("EMPLOYEES", $connection, $dbName))
    {
       $query = "CREATE TABLE EMPLOYEES (
           ID int(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
           NAME VARCHAR(45),
           ADDRESS VARCHAR(90)
         )";

       if(!mysqli_query($connection, $query)) echo("<p>Error creating EMPLOYEES table.</p>");
    }
  }

  /* Check for the existence of a table. */
  function TableExists($tableName, $connection, $dbName) {
    $t = mysqli_real_escape_string($connection, $tableName);
    $d = mysqli_real_escape_string($connection, $dbName);

    $checktable = mysqli_query($connection,
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_NAME = '$t' AND TABLE_SCHEMA = '$d'");

    if(mysqli_num_rows($checktable) > 0) return true;

    return false;
  }
?>
