<?PHP

date_default_timezone_set('Europe/Sofia');

class Main
{
    /**
     * @var PDO
     */
    public static $pdo = null;

    private static $db_username = "microcas_apUser";
    private static $db_password = "aplususer123";
    private static $db_name = "microcas_aplus";

    public static function Connect()
    {
        try {
            Main::$pdo = new PDO("mysql:host=localhost;dbname=" . Main::$db_name, Main::$db_username, Main::$db_password);
            Main::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Connect failed: " . $e->getMessage();
            exit();
        }
    }

    public static function Login($email, $password)
    {
        Helpers::CheckSession(false);

        Functions::CheckEmail($email);
        Functions::CheckPassword($password, false);

        $query = Main::$pdo->prepare("SELECT * FROM accounts WHERE email = ? LIMIT 1");
        $query->bindParam('1', $email);

        $result = $query->execute();

        if ($result) {
            $num_rows = $query->rowCount();

            if ($num_rows == 1) {
                $passwordHash = $query->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $passwordHash['password'])) {
                    $_SESSION['email'] = $email;

                    $query = Main::$pdo->prepare("SELECT `key` FROM cookies WHERE email = ? LIMIT 1");
                    $query->bindParam('1', $email);

                    if (!$query->execute())
                    {
                        echo "Error reading active cookie keys!";
                        exit();
                    }

                    $row = $query->fetch();
                    $key = uniqid("", true);

                    if (!$row)
                    {
                        $query = Main::$pdo->prepare("INSERT INTO cookies (email, `key`) VALUES (?, ?)");
                        $query->bindParam('1', $email);
                        $query->bindParam('2', $key);

                        if (!$query->execute())
                        {
                            echo "Error saving cookie!";
                            exit();
                        }
                    }
                    else
                        $key = $row[0];

                    $cookie = [
                        "email" => $email,
                        "ip" => $_SERVER['REMOTE_ADDR'],
                        "key" => $key,
                    ];

                    setcookie("signedUser", serialize($cookie), time()+60*60*24*256*10); // 10 years
                    echo "Login success!";

                } else echo "Email or password invalid!";
            } else echo "Email or password invalid!";
        } else echo "Error logging in";
    }

    public static function Logout()
    {
        if (isset($_SESSION['email'])) {
            session_destroy();
            setcookie("signedUser", "", time() - 3600);
            echo "Logged out successfully";
        } else {
            echo "Not logged in!";
        }
    }

    public static function Create($email, $password, $firstname, $lastname, $class)
    {
        Helpers::CheckSession(false);

        Functions::CheckEmail($email);
        Functions::CheckPassword($password, true);

        Helpers::CheckExists($email);

        $query = Main::$pdo->prepare("INSERT INTO accounts (firstname, lastname, email, password, type, class, activated) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $query->bindParam('1', $firstname);
        $query->bindParam('2', $lastname);
        $query->bindParam('3', $email);

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $query->bindParam('4', $hashedPassword);

        $accountType = "student";
        $query->bindParam('5', $accountType);

        $query->bindParam('6', $class);

        $result = $query->execute();

        if ($result)
            echo "Account created!";
        else
            echo "Error creating account! Error code: 2";
    }

    public static function CheckUser()
    {
        Helpers::CheckSession(true);

        $user = $_SESSION['email'];
        echo "Currently logged in as: $user" . '<br>';

        $subjectRows = Main::GetSubjects();

        echo '<br>';
        echo "Registered subjects:" . '<br>';

        $firstSubject = null;

        if (count($subjectRows) == 0)
            echo "-" . '<br>';

        foreach ($subjectRows as $subjectRow)
            foreach ($subjectRow as $subject) {
                if ($firstSubject == null)
                    $firstSubject = $subject;

                echo $subject . '<br>';
            }

        echo '<br>';
        echo "Registered grades for $firstSubject:" . '<br>';

        $gradeRows = Main::GetGrades($firstSubject);

        foreach ($gradeRows as $gradeRow)
            foreach ($gradeRow as $grade)
                echo $grade . ' ';
    }

    public static function NewGrade($subject, $grade)
    {
        Helpers::CheckSession(true);

        if ($subject == null || $grade == null) {
            echo "Invalid subject or grade parameter!";
            exit();
        }

        if (!is_numeric($grade) || $grade < 2 || $grade > 6) {
            echo "Grade can only be a number between 2 and 6!";
            exit();
        }

        $query = Main::$pdo->prepare("INSERT INTO grades (email, subject, grade, timestamp) VALUES (?, ?, ?, ?)");
        $query->bindParam('1', $_SESSION['email']);
        $query->bindParam('2', $subject);
        $query->bindParam('3', $grade);

        $timestamp = date("Y-m-d H:i:s");
        $query->bindParam('4', $timestamp);

        $result = $query->execute();

        if ($result)
            echo "Grade saved!";
        else
            echo "Error saving grade!";
    }

    private static function GetSubjects()
    {
        Helpers::CheckSession(true);

        $query = Main::$pdo->prepare("SELECT DISTINCT subject FROM grades WHERE email = ?");
        $query->bindParam('1', $_SESSION['email']);

        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function GetGrades($subject)
    {
        Helpers::CheckSession(true);

        if (!isset($_POST['subject'])) {
            echo "Subject is invalid!";
            exit();
        }

        $query = Main::$pdo->prepare("SELECT grade FROM grades WHERE email = ? AND subject = ?");
        $query->bindParam('1', $_SESSION['email']);
        $query->bindParam('2', $subject);

        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function PrintGrades($subject)
    {
        $gradeRows = Main::GetGrades($subject);

        foreach ($gradeRows as $gradeRow)
                echo $gradeRow[0] . PHP_EOL;
    }

    public static function PrintSubjects()
    {
        $subjectRows = Main::GetSubjects();

        foreach ($subjectRows as $subjectRow)
                echo $subjectRow[0] . PHP_EOL;
    }
}

class Helpers
{
    public static function CheckExists($email, $fatal = true)
    {
        $query = Main::$pdo->prepare("SELECT * FROM accounts WHERE email = ? LIMIT 1");
        $query->bindParam('1', $email);

        $result = $query->execute();

        if ($fatal)
        {
            if (!$result)
                echo "Error creating account! Error code: 1";
            else if ($query->rowCount() > 0) {
                echo "E-mail already registered!";
                exit();
            }
        }

        return $result;
    }

    public static function CheckSession($loggedIn)
    {
        if (isset($_COOKIE['signedUser']) && !isset($_SESSION['email']))
        {
            $cookie = unserialize($_COOKIE['signedUser']);
            if (Helpers::CheckExists($cookie['email'], false) && $_SERVER['REMOTE_ADDR'] == $cookie['ip'])
            {
                $query = Main::$pdo->prepare("SELECT `key` FROM cookies WHERE email = ? LIMIT 1");
                $query->bindParam('1', $cookie['email']);

                $result = $query->execute();

                if ($result)
                    foreach ($query->fetchAll() as $resultRow)
                        foreach ($resultRow as $resultKey)
                            if ($cookie['key'] == $resultKey)
                            {
                                $_SESSION['email'] = $cookie['email'];
                            }
            }
        }
        if (isset($_SESSION['email']) && !$loggedIn) {
            echo "Already logged in!";
            exit();
        } else if ($loggedIn && !isset($_SESSION['email'])) {
            echo "Not logged in!";
            exit();
        }
    }
}

class Functions
{
    public static function CheckEmail($email)
    {
        if ($email == null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "Invalid email!";
            exit();
        }
    }

    public static function CheckPassword($password, $checkLength)
    {
        if ($password == null) {
            echo "Invalid password!";
            exit();
        } else if ($checkLength && strlen($password) < 6) {
            echo "Password must be at least 6 characters long!";
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = htmlspecialchars($_POST['email']);
    $password = htmlspecialchars($_POST['password']);
    $firstname = htmlspecialchars($_POST['firstname']);
    $lastname = htmlspecialchars($_POST['lastname']);
    $class = htmlspecialchars($_POST['class']);

    if (session_status() != PHP_SESSION_ACTIVE)
        session_start();

    Main::Connect();

    if (isset($_POST['register']))
        Main::Create($email, $password, $firstname, $lastname, $class);
    else if (isset($_POST['login']))
        Main::Login($email, $password);
    else if (isset($_POST['checkuser']))
        Main::CheckUser();
    else if (isset($_POST['logout']))
        Main::Logout();
    else if (isset($_POST['newgrade']))
        Main::NewGrade($_POST['subject'], $_POST['grade']);
    else if (isset($_POST['getsubjects']))
        Main::PrintSubjects();
    else if (isset($_POST['getgrades']))
        Main::PrintGrades($_POST['subject']);
} else echo "Hello!";
?>