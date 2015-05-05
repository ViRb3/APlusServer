<?PHP

date_default_timezone_set('Europe/Sofia');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (session_status() != PHP_SESSION_ACTIVE)
        session_start();

    Main::Connect();

    if (isset($_POST['register']))
        Main::Register($_POST['email'], $_POST['password'], $_POST['firstname'], $_POST['lastname'], $_POST['class']);
    else if (isset($_POST['login']))
        Main::Login($_POST['email'], $_POST['password']);
    else if (isset($_POST['checkuser']))
        Main::CheckUser();
    else if (isset($_POST['logout']))
        Main::Logout();
    else if (isset($_POST['newgrade']))
        Main::NewGrade($_POST['subject'], $_POST['grade'], $_POST['code']);
    else if (isset($_POST['getsubjects']))
        Main::PrintSubjects();
    else if (isset($_POST['getgrades']))
        Main::PrintGrades($_POST['subject']);
    else if (isset($_POST['getaccounttype']))
        Main::PrintAccountType();
    else if (isset($_POST['getstudents']))
        Main::PrintStudents();
    else if (isset($_POST['getaccounts'])) // optional arguments
    	Main::PrintAccounts($_POST['class'], $_POST['type'], $_POST['unactivatedonly']);
    else if (isset($_POST['getstudentemail']))
        Main::PrintStudentEmail($_POST['firstname'], $_POST['lastname'], $_POST['class']);

} else echo "Hello!";