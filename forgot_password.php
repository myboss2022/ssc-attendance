<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/password_reset_mailer.php';

if (is_admin()) {
    header('Location: admin/index.php');
    exit;
}

$conn = db();
$error = '';
$notice = '';
$complete = false;
if (!empty($_SESSION['password_reset_complete'])) {
    $complete = true;
    unset($_SESSION['password_reset_complete']);
}
if (empty($_SESSION['password_reset_csrf'])) {
    $_SESSION['password_reset_csrf'] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION['password_reset_flash'])) {
    $notice = $_SESSION['password_reset_flash'];
    unset($_SESSION['password_reset_flash']);
}
if (!empty($_SESSION['password_reset_error'])) {
    $error = $_SESSION['password_reset_error'];
    unset($_SESSION['password_reset_error']);
}

$reset = $_SESSION['password_reset'] ?? null;
if ($reset && (int)$reset['expires_at'] < time()) {
    unset($_SESSION['password_reset']);
    $reset = null;
    $error = 'The reset code expired. Request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['password_reset_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'This page expired. Reload it and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'request_code') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $lastRequest = (int)($_SESSION['password_reset_last_request'] ?? 0);
            if (time() - $lastRequest < 60) {
                $_SESSION['password_reset_error'] = 'Please wait one minute before requesting another reset code.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['password_reset_error'] = 'Enter a valid email address.';
            } else {
                $_SESSION['password_reset_last_request'] = time();
                unset($_SESSION['password_reset']);
                $accounts = [];
                foreach (['admin' => 'admin_users', 'ssc' => 'ssc_users'] as $type => $table) {
                    $lookup = $conn->prepare("SELECT id,full_name FROM {$table} WHERE lower(email)=lower(?) LIMIT 1");
                    $lookup->bind_param('s', $email);
                    $lookup->execute();
                    $account = $lookup->get_result()->fetch_assoc();
                    if ($account) {
                        $accounts[] = [
                            'id' => (int)$account['id'],
                            'type' => $type,
                            'name' => $account['full_name'],
                        ];
                    }
                }

                if (!$accounts) {
                    $_SESSION['password_reset_error'] = 'No Admin or SSC account was found with that email address.';
                } else {
                    $code = sprintf('%06d', random_int(0, 999999));
                    try {
                        send_password_reset_code($email, $code);
                        $_SESSION['password_reset'] = [
                            'email' => $email,
                            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                            'expires_at' => time() + 600,
                            'attempts' => 0,
                            'accounts' => $accounts,
                            'verified' => false,
                        ];
                        $_SESSION['password_reset_flash'] = 'A six-digit reset code was sent to your email. Enter it below.';
                    } catch (Throwable $exception) {
                        error_log('Password reset email delivery failed: '.$exception->getMessage());
                        $_SESSION['password_reset_error'] = 'We could not send the reset code. Check the email settings or try again later.';
                    }
                }
            }
            header('Location: forgot_password.php');
            exit;
        }

        $reset = $_SESSION['password_reset'] ?? null;
        if (!$reset || (int)$reset['expires_at'] < time()) {
            unset($_SESSION['password_reset']);
            $reset = null;
            $error = 'The reset code expired or is not available. Request a new one.';
        } elseif ($action === 'verify_code') {
            if (!empty($reset['verified'])) {
                header('Location: forgot_password.php');
                exit;
            }
            $code = trim((string)($_POST['code'] ?? ''));
            if ((int)$reset['attempts'] >= 5) {
                unset($_SESSION['password_reset']);
                $reset = null;
                $error = 'Too many incorrect attempts. Request a new code.';
            } else {
                $_SESSION['password_reset']['attempts'] = (int)$reset['attempts'] + 1;
                if (preg_match('/^\d{6}$/', $code) && password_verify($code, $reset['code_hash'])) {
                    $_SESSION['password_reset']['verified'] = true;
                    $_SESSION['password_reset']['verified_at'] = time();
                    $_SESSION['password_reset_flash'] = 'Code verified. Choose your account and set a new password.';
                    header('Location: forgot_password.php');
                    exit;
                } elseif ((int)$_SESSION['password_reset']['attempts'] >= 5) {
                    unset($_SESSION['password_reset']);
                    $reset = null;
                    $error = 'Too many incorrect attempts. Request a new code.';
                } else {
                    $error = 'That code is incorrect. Check the email and try again.';
                }
            }
        } elseif ($action === 'reset_password' && !empty($reset['verified'])) {
            $accountType = (string)($_POST['account_type'] ?? '');
            $selectedAccount = null;
            foreach ($reset['accounts'] as $account) {
                if ($account['type'] === $accountType) {
                    $selectedAccount = $account;
                    break;
                }
            }
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');
            if (!$selectedAccount) {
                $error = 'Select the account whose password you want to reset.';
            } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
                $error = 'Use a password between 8 and 72 bytes.';
            } elseif (!hash_equals($newPassword, $confirmPassword)) {
                $error = 'The passwords do not match.';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $id = (int)$selectedAccount['id'];
                $email = $reset['email'];
                if ($accountType === 'ssc') {
                    $update = $conn->prepare('UPDATE ssc_users SET password_hash=?,must_change_password=0 WHERE id=? AND lower(email)=lower(?)');
                } else {
                    $update = $conn->prepare('UPDATE admin_users SET password_hash=? WHERE id=? AND lower(email)=lower(?)');
                }
                $update->bind_param('sis', $passwordHash, $id, $email);
                $update->execute();
                if ($update->affected_rows > 0) {
                    unset($_SESSION['password_reset']);
                    $_SESSION['password_reset_flash'] = 'Your password was reset. You can now sign in with the new password.';
                    $_SESSION['password_reset_complete'] = true;
                    header('Location: forgot_password.php');
                    exit;
                }
                $error = 'That account could not be updated. Request a new reset code and try again.';
            }
        } else {
            $error = 'Verify your reset code before setting a new password.';
        }
    }
}

$reset = $_SESSION['password_reset'] ?? null;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Reset Password - SSC</title>
<link rel="icon" type="image/png" href="/logo/SSC.png">
<link rel="apple-touch-icon" href="/logo/SSC.png">
<style>
  :root{font-family:Arial,Helvetica,sans-serif;color:#1e293b;background:#f5f3ff}*{box-sizing:border-box}
  body{min-height:100vh;min-height:100dvh;margin:0;background:linear-gradient(135deg,#ede9fe 0%,#fff 52%,#e0e7ff 100%);display:flex;align-items:center}
  .wrap{width:100%;max-width:448px;margin:auto;padding:24px}.brand{margin-bottom:24px;text-align:center}.brand img{width:48px;height:48px;object-fit:cover;border-radius:14px;vertical-align:middle;margin-right:10px}.brand strong{font-size:20px}
  .card{padding:20px;background:#fff;border-radius:16px;box-shadow:0 12px 30px rgba(30,41,59,.12)}h1{margin:0 0 8px;font-size:22px;color:#1e1b4b}p{margin:0 0 18px;color:#64748b;font-size:14px;line-height:1.5}
  .field{margin-bottom:14px}.field label{display:block;font-size:14px;color:#475569}.field input,.field select{width:100%;margin-top:5px;padding:11px 12px;border:1px solid #cbd5e1;border-radius:7px;font:inherit}.field input:focus,.field select:focus{border-color:#8b5cf6;outline:3px solid rgba(139,92,246,.16)}
  .button{width:100%;border:0;border-radius:8px;background:#7c3aed;color:#fff;padding:11px 16px;font:inherit;font-weight:600;cursor:pointer}.button:hover{background:#6d28d9}
  .message{margin-bottom:14px;padding:11px 12px;border-radius:8px;font-size:14px;line-height:1.45}.error{background:#fee2e2;color:#991b1b}.notice{background:#ede9fe;color:#4c1d95}.success{background:#dcfce7;color:#166534}
  a{display:block;margin-top:16px;text-align:center;color:#6d28d9;font-size:14px;text-decoration:none}a:hover{text-decoration:underline}
  @media(max-width:520px){.wrap{padding:16px}.card{padding:17px}}
</style>
</head>
<body>
<main class="wrap">
  <div class="brand"><img src="logo/SSC.png" alt="SSC logo"><strong>SSC Attendance</strong></div>
  <section class="card">
    <h1><?php echo $complete ? 'Password reset complete' : 'Reset your password'; ?></h1>
    <?php if ($error): ?><div class="message error" role="alert"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="message <?php echo $complete ? 'success' : 'notice'; ?>" role="status"><?php echo h($notice); ?></div><?php endif; ?>
    <?php if ($complete): ?>
      <p>Use your new password to sign in.</p>
      <a href="login.php">Back to login</a>
    <?php elseif (!$reset): ?>
      <p>Enter the Gmail address saved on your Admin or SSC account. If it matches an account, we will email a one-time code.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['password_reset_csrf']); ?>">
        <input type="hidden" name="action" value="request_code">
        <div class="field"><label for="email">Account email</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="150"></div>
        <button class="button" type="submit">Send reset code</button>
      </form>
      <a href="login.php">Back to login</a>
    <?php elseif (empty($reset['verified'])): ?>
      <p>If the address is linked to an account, a six-digit code should arrive shortly. The code expires in 10 minutes.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['password_reset_csrf']); ?>">
        <input type="hidden" name="action" value="verify_code">
        <div class="field"><label for="code">Six-digit code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div>
        <button class="button" type="submit">Verify code</button>
      </form>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['password_reset_csrf']); ?>">
        <input type="hidden" name="action" value="request_code">
        <input type="hidden" name="email" value="<?php echo h($reset['email']); ?>">
        <button class="button" type="submit" style="margin-top:10px;background:#475569">Send a new code</button>
      </form>
      <a href="login.php">Back to login</a>
    <?php else: ?>
      <p>Choose the account to update and enter a new password.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['password_reset_csrf']); ?>">
        <input type="hidden" name="action" value="reset_password">
        <?php if (count($reset['accounts']) > 1): ?>
          <div class="field"><label for="account_type">Account</label><select id="account_type" name="account_type" required><option value="">Choose an account</option><?php foreach ($reset['accounts'] as $account): ?><option value="<?php echo h($account['type']); ?>"><?php echo h(($account['type'] === 'admin' ? 'Admin' : 'SSC user').' - '.$account['name']); ?></option><?php endforeach; ?></select></div>
        <?php else: ?>
          <input type="hidden" name="account_type" value="<?php echo h($reset['accounts'][0]['type']); ?>">
        <?php endif; ?>
        <div class="field"><label for="new_password">New password</label><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
        <div class="field"><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
        <button class="button" type="submit">Save new password</button>
      </form>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
