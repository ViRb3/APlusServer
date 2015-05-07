<?php
include_once 'Main.php';

class Functions
{
    public static function CheckEmail($email)
    {
        if ($email == null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo 'Invalid email!';
            exit();
        }
    }

    public static function CheckPassword($password)
    {
        if (!Functions::IsSHA256($password))
        {
            echo 'Password is not a valid SHA-256 hash!';
            exit();
        }
    }

    public static function CheckClass($class)
    {
        if (!Functions::IsValidClass($class))
        {
            echo 'Invalid class specified!';
            exit();
        }
    }

    public static function IsSHA256($input)
    {
        return preg_match('/^[A-Fa-f0-9]{64}$/i', $input);
    }

    public static function IsValidSpecial($password)
    {
        return preg_match('/[A-Za-zА-Яа-я0-9!-+]/u', $password);
    }

    public static function IsValidName($name)
    {
        return preg_match('/[A-Za-zА-Яа-я-]/u', $name);
    }

    public static function IsValidClass($class)
    {
        return (preg_match('/[0-9]{1,2}[A-Z]/', $class, $matches) && $matches[0] == $class);
    }

    public static function Decrypt($data)
    {
        $data = base64_decode($data);
        $IVLength = 32;

        $IV = substr($data, strlen($data) - $IVLength);
        $data = substr($data, 0, strlen($data) - $IVLength);

        $key = Main::$key;

        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_CBC, $IV);
        $decrypted = Functions::Unpad($decrypted, mcrypt_get_block_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC));

        return $decrypted;
    }

    private static function Unpad($data, $blockSize)
    {
        $dataLength = strlen($data);

        if ($dataLength % $blockSize != 0)
        {
            throw new Exception("Padded plaintext cannot be divided by the block size!");
        }

        $padSize = ord($data[$dataLength - 1]);

        if ($padSize === 0)
        {
            throw new Exception("Zero padding found instead of PKCS#7 padding!");
        }

        if ($padSize > $blockSize)
        {
            throw new Exception("Incorrect amount of PKCS#7 padding for blocksize!");
        }

        $padding = substr($data, -1 * $padSize);
        if (substr_count($padding, chr($padSize)) != $padSize)
        {
            throw new Exception("Invalid PKCS#7 padding encountered!");
        }

        return substr($data, 0, $dataLength - $padSize);
    }

    public static function CheckExists($email, $fatal = true)
    {
        $query = Main::$pdo->prepare('SELECT * FROM `accounts` WHERE `email` = ? LIMIT 1');
        $query->bindParam('1', $email);

        $result = $query->execute();

        if ($fatal)
        {
            if (!$result)
                echo 'Error code: 1';
            else if ($query->rowCount() > 0) {
                echo 'E-mail already registered!';
                exit();
            }
        }

        return $result;
    }

    public static function CheckSession($mustLoggedIn)
    {
        if (isset($_SESSION['email']) && !Functions::IsActivated($_SESSION['email']))
            Functions::DestroySession();

        if (isset($_COOKIE['signedUser']) && !isset($_SESSION['email']))
        {
            $cookie = unserialize($_COOKIE['signedUser']);
            if (Functions::CheckExists($cookie['email'], false) && $_SERVER['REMOTE_ADDR'] == $cookie['ip'])
            {
                $query = Main::$pdo->prepare('SELECT `key` FROM `cookies` WHERE `email` = ? LIMIT 1');
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

        if (isset($_SESSION['email']) && !$mustLoggedIn) {
            echo 'Already logged in!';
            exit();
        } else if ($mustLoggedIn && !isset($_SESSION['email'])) {
            echo 'Not logged in!';
            exit();
        }
    }

    public static function CheckActivated($email)
    {
        if (!Functions::IsActivated($email))
        {
            echo 'Account not activated!';
            exit();
        }
    }

    public static function IsActivated($email)
    {
        $query = Main::$pdo->prepare('SELECT `activated` FROM `accounts` WHERE `email` = ? LIMIT 1');
        $query->bindParam('1', $email);

        $result = $query->execute();
        if (!$result || $query->fetch()[0] != 1)
            return false;

        return true;
    }

    public static function DestroySession()
    {
        unset($_COOKIE['signedUser']);
        setcookie('signedUser', '', time() - 3600);

        if (session_status() == PHP_SESSION_ACTIVE)
        {
            session_unset();
            session_destroy();
        }
    }
}