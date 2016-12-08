<?PHP
include_once 'Functions.php';
include_once 'Main.php';

date_default_timezone_set('Europe/Sofia');

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    if (session_status() != PHP_SESSION_ACTIVE)
        session_start();

    SecurePost('subject');
    SecurePost('grade');
    SecurePost('class');
    SecurePost('type');
    SecurePost('firstname');
    SecurePost('lastname');
    SecurePost('data');
    SecurePost('email');

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
    else if (isset($_POST['newgrades']))
        Main::NewGrades($_POST['data']);
    else if (isset($_POST['getsubjects']))
        Main::PrintSubjects();
    else if (isset($_POST['getgrades']))
        Main::PrintGrades($_POST['subject']);
    else if (isset($_POST['getaccounttype']))
        Main::PrintAccountType();
    else if (isset($_POST['getstudents']))
        Main::PrintStudents($_POST['class']); // optional parameters
    else if (isset($_POST['getaccounts']))
    	Main::PrintAccounts($_POST['class'], $_POST['type'], $_POST['unactivatedonly']); // optional parameters
    else if (isset($_POST['getstudentemail']))
        Main::PrintStudentEmail($_POST['firstname'], $_POST['lastname'], $_POST['class']);
    else if (isset($_POST['updateaccounts']))
        Main::UpdateAccounts($_POST['data']);
    else if (isset($_POST['activateaccount']))
        Main::Activate($_POST['email']);
    else
        echo 'Nothing to do!';

} else echo 'Hello!';

function SecurePost($postName)
{
    if (isset($_POST[$postName]))
        $_POST[$postName] = htmlspecialchars($_POST[$postName]);
}