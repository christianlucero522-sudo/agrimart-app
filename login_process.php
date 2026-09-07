<?php

session_start();

require_once "config.php";

/*
|--------------------------------------------------------------------------
| Only accept POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: login.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Get form data
|--------------------------------------------------------------------------
*/

$email = trim(
    $_POST['email'] ?? ''
);

$password =
    $_POST['password'] ?? '';


/*
|--------------------------------------------------------------------------
| Validate fields
|--------------------------------------------------------------------------
*/

if (
    $email === '' ||
    $password === ''
) {

    $_SESSION['login_error'] =
        "Please enter your email and password.";

    header("Location: login.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Find user
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT
        user_id,
        full_name,
        email,
        password,
        role,
        status

    FROM users

    WHERE email = ?

    LIMIT 1

";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: "
        . $conn->error
    );

}


$stmt->bind_param(
    "s",
    $email
);


$stmt->execute();


$result =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| User not found
|--------------------------------------------------------------------------
*/

if (
    $result->num_rows !== 1
) {

    $_SESSION['login_error'] =
        "Invalid email or password.";

    header("Location: login.php");

    exit;
}


$user =
    $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| Verify password
|--------------------------------------------------------------------------
*/

if (
    !password_verify(
        $password,
        $user['password']
    )
) {

    $_SESSION['login_error'] =
        "Invalid email or password.";

    header("Location: login.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Check account status
|--------------------------------------------------------------------------
*/

if (
    $user['status'] !== 'active'
) {

    $_SESSION['login_error'] =
        "Your account is not active.";

    header("Location: login.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Successful login
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);


$_SESSION['user_id'] =
    $user['user_id'];

$_SESSION['full_name'] =
    $user['full_name'];

$_SESSION['email'] =
    $user['email'];

$_SESSION['role'] =
    $user['role'];


/*
|--------------------------------------------------------------------------
| Redirect based on role
|--------------------------------------------------------------------------
*/

if ($user['role'] === 'admin') {

    header("Location: admin_dashboard.php");

} else {

    header("Location: index.php");

}


exit;