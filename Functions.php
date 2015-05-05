<?php

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
        else if(!Functions::IsPasswordValid($password)) {
            echo "Invalid characters found in password!";
            exit();
        }
    }

    public static function IsPasswordValid($password)
    {
        return !preg_match('/[^A-Za-zА-Яа-я0-9!-+]/u', $password);
    }

    public static function Decrypt($data)
    {
        $data = base64_decode($data);
        $IVLength = 32;

        $IV = substr($data, strlen($data) - $IVLength);
        $data = substr($data, 0, strlen($data) - $IVLength);

        $key = Main::$key;

        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_CBC, $IV);
        return $decrypted;
    }

    public static function CheckExists($email, $fatal = true)
    {
        $query = Main::$pdo->prepare("SELECT * FROM `accounts` WHERE `email` = ? LIMIT 1");
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
            if (Functions::CheckExists($cookie['email'], false) && $_SERVER['REMOTE_ADDR'] == $cookie['ip'])
            {
                $query = Main::$pdo->prepare("SELECT `key` FROM `cookies` WHERE `email` = ? LIMIT 1");
                $query->bindParam('1', $cookie['email']);

                $result = $query->execute();

                if ($result)
                {
                    $row = $query->fetch();

                    if ($cookie['key'] == $row[0])
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