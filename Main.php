<?php
include_once 'Functions.php';

class Main
{
    /**
     * @var PDO
     */
    public static $pdo = null;

    // BEGIN SETTINGS ------------------------------------------------------------------->
    private static $db_username = 'microcas_apUser'; // Database username
    private static $db_password = 'aplususer123'; // Database password
    private static $db_name = 'microcas_aplus'; // Database name

    public static $key = 'v0,|m4Q/9K9mN\'z*{RGL0@7eL2R8pHq4'; // Code decryption key
    public static $startNumber = 18514; // Code decryption IV XOR randomizer
    //<-----------------------------------------------------------------------END SETTINGS

    public static function Connect()
    {
        try {
            Main::$pdo = new PDO('mysql:host=localhost;dbname=' . Main::$db_name  . ';charset=UTF8', Main::$db_username,
                Main::$db_password, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

            Main::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo 'Connect failed: ' . $e->getMessage();
            exit();
        }
    }

    public static function Login($email, $password)
    {
        Functions::CheckSession(false);

        Functions::CheckEmail($email);
        Functions::CheckPassword($password);

        $query = Main::$pdo->prepare('SELECT * FROM `accounts` WHERE `email` = ? LIMIT 1');
        $query->bindParam('1', $email);

        $result = $query->execute();

        if ($result) {
            $num_rows = $query->rowCount();

            if ($num_rows == 1) {
                $passwordHash = $query->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $passwordHash['password'])) {

                    Functions::CheckActivated($email);

                    $_SESSION['email'] = $email;

                    $query = Main::$pdo->prepare('SELECT `key` FROM `cookies` WHERE `email` = ? LIMIT 1');
                    $query->bindParam('1', $email);

                    if (!$query->execute())
                    {
                        echo 'Error reading active cookie keys!';
                        exit();
                    }

                    $row = $query->fetch();
                    $key = uniqid('', true);

                    if (!$row)
                    {
                        $query = Main::$pdo->prepare('INSERT INTO `cookies` (`email`, `key`) VALUES (?, ?)');
                        $query->bindParam('1', $email);
                        $query->bindParam('2', $key);

                        if (!$query->execute())
                        {
                            echo 'Error saving cookie!';
                            exit();
                        }
                    }
                    else $key = $row[0];

                    $cookie = [
                        'email' => $email,
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'key' => $key,
                    ];

                    setcookie('signedUser', serialize($cookie), time()+60*60*24*256*10); // 10 years
                    echo 'Login success!';

                } else echo 'Email or password invalid!';
            } else echo 'Email or password invalid!';
        } else echo 'Error logging in';
    }

    public static function Logout()
    {
        if (isset($_SESSION['email'])) {
            Functions::DestroySession();
            echo 'Logged out successfully';
        } else {
            echo 'Not logged in!';
        }
    }

    public static function Register($email, $password, $firstname, $lastname, $class)
    {
        Functions::CheckSession(false);

        Functions::CheckEmail($email);
        Functions::CheckPassword($password);

        Functions::CheckExists($email);

        Functions::CheckClass($class);

        $query = Main::$pdo->prepare('INSERT INTO `accounts` (`firstname`, `lastname`, `email`, `password`, `type`, `class`, `activated`) VALUES (?, ?, ?, ?, ?, ?, 0)');
        $query->bindParam('1', $firstname);
        $query->bindParam('2', $lastname);
        $query->bindParam('3', $email);

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $query->bindParam('4', $hashedPassword);

        $accountType = 'student';
        $query->bindParam('5', $accountType);

        $query->bindParam('6', $class);

        $result = $query->execute();

        if ($result)
            echo 'Account created!';
        else
            echo 'Error creating account! Error code: 2';
    }

    public static function CheckUser()
    {
        Functions::CheckSession(true);

        $query = Main::$pdo->prepare('SELECT `firstname`, `lastname`, `class` FROM `accounts` WHERE `email` = ?');
        $query->bindParam('1', $_SESSION['email']);
        $query->execute();

        $result = $query->fetch();
        if (!$result)
        {
            echo 'Error loading account data!';
            return;
        }

        echo 'Currently logged in as: ' . $result[0]. ' ' . $result[1]. ' '. $result[2]. '<br>';

        $subjectRows = Main::GetSubjects();

        echo '<br>';
        echo 'Registered subjects:' . '<br>';

        foreach ($subjectRows as $subjectRow)
            foreach ($subjectRow as $subject) {
                echo "$subject: ";

                $gradeRows = Main::GetGrades($subject);

                foreach ($gradeRows as $gradeRow)
                    foreach ($gradeRow as $grade)
                        echo $grade . ' ';

                echo '<br>';
            }

        if (count($subjectRows) == 0)
            echo '-' . '<br>';
    }

    public static function NewGrade($subject, $grade, $code)
    {
        Functions::CheckSession(true);
        Main::CheckTeacher(true);

        if ($subject == null || $grade == null || $code == null) {
            echo 'Invalid subject, grade or code parameter!';
            exit();
        }

        if (!is_numeric($grade) || $grade < 2 || $grade > 6) {
            echo 'Grade can only be a number between 2 and 6!';
            exit();
        }

        $studentEmail = trim(Functions::Decrypt($code)); // user@email.com

        $query = Main::$pdo->prepare('SELECT `email` FROM `accounts` WHERE `email` = ?');
        $query->bindParam('1', $studentEmail);

        $result = $query->execute();

        if (!$result) {
            echo 'Error reading e-mail!';
            exit();
        }

        if (!$query->fetch()) {
            echo "Given e-mail: '$studentEmail' is not registered!";
            exit();
        }

        $query = Main::$pdo->prepare('SELECT `code` FROM `grades` WHERE `code` = ?');
        $query->bindParam('1', $code);
        $query->execute();

        if ($query->fetch()) // if code is found in database
        {
            echo 'Code already used!' . PHP_EOL;
            return;
        }

        $query = Main::$pdo->prepare('INSERT INTO `grades` (email, `subject`, `grade`, `timestamp`, `code`) VALUES (?, ?, ?, ?, ?)');
        $query->bindParam('1', $studentEmail);
        $query->bindParam('2', $subject);
        $query->bindParam('3', $grade);

        $timestamp = date('Y-m-d H:i:s');
        $query->bindParam('4', $timestamp);

        $query->bindParam('5', $code);

        $result = $query->execute();

        if ($result)
        {
            echo 'Grade saved!' . PHP_EOL;
            $student = Main::GetUserData($studentEmail);
            echo "Graded student: $student in $subject with $grade" . PHP_EOL;
        }
        else
            echo 'Error saving grade!';
    }

    public static function NewGrades($data)
    {
        $pieceSeparator = '-||-';
        $dataSeparator = '-|-';

        $updatePieces = explode($pieceSeparator, $data);

        foreach($updatePieces as $piece)
        {
            if (empty(trim($piece)))
                continue;

            $pieceData = explode($dataSeparator, $piece);
            if (count($pieceData) != 3) // SUBJECT, GRADE, CODE
            {
                echo "Data: '$piece' is not in a correct format!";
                return;
            }

            Main::NewGrade($pieceData[0], $pieceData[1], $pieceData[2]);
        }
    }

    public static function UpdateAccounts($data)
    {
        $pieceSeparator = '-||-';
        $dataSeparator = '-|-';

        $updatePieces = explode($pieceSeparator, $data);

        foreach($updatePieces as $piece)
        {
            if (empty(trim($piece)))
                continue;

            $pieceData = explode($dataSeparator, $piece);
            if (count($pieceData) != 3) // EMAIL, COLUMN, DATA
            {
                echo "Data: '$piece' is not in a correct format!";
                return;
            }

            Functions::CheckEmail($pieceData[0]);
            $column = null;

            switch($pieceData[1])
            {
                case 0:
                    $column = 'email';
                    break;
                case 1:
                    $column = 'firstname';
                    break;
                case 2:
                    $column = 'lastname';
                    break;
                case 3:
                    $column = 'class';
                    break;
                case 4:
                    $column = 'type';
                    break;
                case 5:
                    $column = 'activated';
                    break;
            }

            if ($column == null)
            {
                echo 'Invalid column index given!';
                return;
            }

            $query = Main::$pdo->prepare("UPDATE `accounts` SET ? = ? WHERE `email` = ? LIMIT 1");
            $query->bindParam('1', $column);
            $query->bindParam('2', $pieceData[2]);
            $query->bindParam('3', $pieceData[0]);

            $result = $query->execute();

            if (!$result)
            {
                echo 'Error updating record!';
                return;
            }
        }

        echo 'Records updated!';
    }

    public static function Activate($email)
    {
        Main::CheckAdmin(true);

        $query = Main::$pdo->prepare('SELECT 1 FROM `accounts` WHERE `email` = ?');
        $query->bindParam('1', $email);

        $result = $query->execute();

        if (!$result)
        {
            echo 'Error checking e-mail!';
            exit();
        }

        if (!$query->fetch()) {
            echo 'Invalid e-mail' . PHP_EOL;
            return;
        }

        $query = Main::$pdo->prepare('UPDATE accounts SET activated = 1 WHERE email = ?');
        $query->bindParam('1', $code);

        $result = $query->execute();

        if (!$result)
        {
            echo 'Error activating account!';
            exit();
        }

        echo 'Account activated!';
    }

    // BEGIN PRIVATE FUNCTIONS ------------------------------------------------------------->
    private static function GetSubjects()
    {
        Functions::CheckSession(true);

        $query = Main::$pdo->prepare('SELECT DISTINCT `subject` FROM `grades` WHERE `email` = ?');
        $query->bindParam('1', $_SESSION['email']);

        $result = $query->execute();

        if (!$result)
        {
            echo 'Error getting account subjects!';
            exit();
        }

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function GetGrades($subject)
    {
        Functions::CheckSession(true);

        if (empty($subject)) {
            echo 'Subject is invalid!';
            exit();
        }

        $query = Main::$pdo->prepare('SELECT `grade` FROM `grades` WHERE `email` = ? AND `subject` = ?');
        $query->bindParam('1', $_SESSION['email']);
        $query->bindParam('2', $subject);

        $result = $query->execute();

        if (!$result)
        {
            echo 'Error getting account grades!';
            exit();
        }

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function GetAccountType()
    {
        Functions::CheckSession(true);

        $query = Main::$pdo->prepare('SELECT `type` FROM `accounts` WHERE `email` = ?');
        $query->bindParam('1', $_SESSION['email']);

        $result = $query->execute();

        if (!$result)
        {
            echo 'Error getting account type!';
            exit();
        }

        return $query->fetch()[0];
    }

    private static function GetStudents($class = null)
    {
        Functions::CheckSession(true);
        Main::CheckAdmin(true);

        $queryBuilder = 'SELECT `email`, `firstname`, `lastname`, `class` FROM `accounts` WHERE `type` = ? AND `activated` = 1';

        if (isset($class) && !empty(trim($class)))
        {
            Functions::CheckClass($class);
            $queryBuilder .= ' AND `class` = ?';
        }

        $query = Main::$pdo->prepare($queryBuilder);

        $type = 'student';
        $query->bindParam('1', $type);

        if (isset($class) && !empty(trim($class)))
        {
            $query->bindParam('2', $class);
        }

        if (!$query->execute()) {
            echo 'Error reading student data!';
            exit();
        }

        return $query->fetchAll();
    }

    private static function GetAccounts($class = null, $type = null, $unactivatedOnly = null)
    {
        Functions::CheckSession(true);
        Main::CheckAdmin(true);

        $query = null;
        $queryBuilder = 'SELECT `email`, `firstname`, `lastname`, `class`, `type`, `activated` FROM `accounts` WHERE `type` != ?';

        if (isset($class) && !empty(trim($class)))
        {
            Functions::CheckClass($class);
            $queryBuilder .= ' AND `class` = ?';
        }

        if (isset($type) && !empty(trim($type)))
        {
            $queryBuilder .= ' AND `type` = ?';
        }

        if (isset($unactivatedOnly))
        {
            $queryBuilder .= ' AND `activated` = 0';
        }

        $query = Main::$pdo->prepare($queryBuilder);

        $parameterCounter = 2;
        $admin = 'admin';
        $query->bindParam('1', $admin);

        if (isset($class) && !empty(trim($class)))
        {
            $query->bindParam($parameterCounter++, $class);
        }

        if (isset($type) && !empty(trim($type)))
        {
            $query->bindParam($parameterCounter, $type);
        }

        if (!$query->execute()) {
            echo 'Error reading account data!';
            exit();
        }

        return $query->fetchAll();
    }

    private static function GetStudentEmail($firstName, $lastName, $class)
    {
        Functions::CheckSession(true);
        Main::CheckTeacher(true);

        Functions::CheckClass($class);

        $query = Main::$pdo->prepare('SELECT `email` FROM `accounts` WHERE `firstname` = ? AND `lastname` = ? AND `class` = ? AND `type` = ? LIMIT 1');
        $query->bindParam('1', $firstName);
        $query->bindParam('2', $lastName);
        $query->bindParam('3', $class);

        $type = 'student';
        $query->bindParam('4', $type);

        if (!$query->execute()) {
            echo 'Error resolving student e-mail!';
            exit();
        }

        $row = $query->fetch();

        if (!$row) {
            echo 'No student found that matches the given criteria!';
            exit();
        }

        return $row[0];
    }

    private static function GetUserData($email)
    {
        Functions::CheckSession(true);
        Main::CheckTeacher(true);

        if (!isset($email) || empty($email)) {
            echo 'E-mail is invalid!';
            exit();
        }

        $query = Main::$pdo->prepare('SELECT `firstname`, `lastname`, `class` FROM `accounts` WHERE `email` = ?');
        $query->bindParam('1', $email);

        if (!$query->execute()) {
            echo 'Error resolving user data!';
            exit();
        }

        $row = $query->fetch();

        if (!$row) {
            echo 'No user found with given E-mail!';
            exit();
        }

        return $row[0] . ' ' . $row[1] . ' ' . $row[2];
    }

    private static function CheckTeacher($fatal)
    {
        if (Main::GetAccountType() != 'teacher') {
            if ($fatal)
            {
                echo 'Only teachers can use this function!';
                exit();
            }
            return false;
        }
        return true;
    }

    private static function CheckAdmin($fatal)
    {
        if (Main::GetAccountType() != 'admin') {
            if ($fatal)
            {
                echo 'Only administrators can use this function!';
                exit();
            }
            return false;
        }
        return true;
    }
    //<--------------------------------------------------------------- END PRIVATE FUNCTIONS

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

    public static function PrintAccountType()
    {
        echo Main::GetAccountType();
    }

    public static function PrintStudents($class)
    {
        $students = Main::GetStudents($class);

        foreach($students as $row)
            echo $row[0] . ' ' . $row[1] . ' ' . $row[2] . ' ' . $row[3] . PHP_EOL;
    }

    public static function PrintAccounts($class, $type, $unactivatedOnly)
    {
        $accounts = Main::GetAccounts($class, $type, $unactivatedOnly);

        foreach($accounts as $row)
            echo $row[0] . ' ' . $row[1] . ' ' . $row[2] . ' ' . $row[3] . ' ' . $row[4] . ' ' . $row[5] . PHP_EOL;
    }

    public static function PrintStudentEmail($firstName, $lastName, $class)
    {
        echo Main::GetStudentEmail($firstName, $lastName, $class);
    }

    public static function PrintUnactivatedStudents()
    {
        $query = Main::$pdo->prepare('SELECT * FROM `accounts` WHERE `activated` = 0');
        $result = $query->execute();

        if (!$result)
        {
            echo 'Error retrieving unactivated students!';
            exit();
        }
        else if ($query->rowCount() == 0)
        {
            echo 'No unactivated students!';
            exit();
        }
        else
        {
            foreach($query->fetchAll() as $row)
                echo $row['firstname'] . ' ' . $row['lastname'] . ' ' . $row['class'];
        }
    }
}