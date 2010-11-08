<?php

// Copyright © 2010 Arthur Furlan (afurlan) <afurlan@afurlan.org>
//
// This is a dirty little script based on some code from Leonardo Chiquitto,
// it is old and not fancy at all, but it does what is needed: change the
// user password thru a web interface. There are nasty hardcoded non-tableless
// ugliness in here, you have been warned. This version is based on the version
// of Felipe Augusto van de Wiel (faw), who made some changes in the original
// code from Leonardo Chiquito, and different of what is said above, from now
// on the code is tableless and HTML5/CSS validated.
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License along
// with this program; if not, write to the Free Software Foundation, Inc., 
// 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
//
// Here be the dragons.

// @TODO: Add some i18n feature.

error_reporting(0);

// Configure the server and basedn
define('LDAP_SERVER', 'ldap.example.com');
define('LDAP_BASEDN', 'ou=People,dc=example,dc=com');
define('ADMIN_ENABLED', False);

// Configure the page encoding
define('PAGE_CHARSET', 'utf-8');
header('Content-Type: text/html;charset=' . PAGE_CHARSET);

function redirect_message($message, $type='err') {
    $querystring = '';
    if ($_GET['type'] == 'advanced')
        $querystring = 'type=advanced&';
    exit(header("location: ?{$querystring}{$type}={$message}"));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (empty($_POST['newpass']) || empty($_POST['cnewpass']))
        redirect_message('dados-insuficientes');

    if ($_POST['newpass'] != $_POST['cnewpass'])
        redirect_message('senhas-nao-conferem');

    if (!$ldap_conn = ldap_connect(LDAP_SERVER))
        redirect_message('servico-indisponivel');
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);

    $ldap_userdn = "uid={$_POST['login']}," . LDAP_BASEDN;
    if (isset($_GET['type']) && $_GET['type'] == 'advanced') {
        if (!ldap_bind($ldap_conn, $_POST['admindn'], $_POST['adminpass']))
            redirect_message('senha-invalida');
    } else {
        if (!ldap_bind($ldap_conn, $ldap_userdn, $_POST['oldpass']))
            redirect_message('senha-invalida');
    }

    $crypted_pass = sprintf('{crypt}%s',
        crypt($_POST['newpass'], '$1$dmXHOahU$'));
    $newinfo['userpassword'] = $crypted_pass;

    if (ldap_modify($ldap_conn, $ldap_userdn, $newinfo))
        redirect_message('senha-alterada', 'msg');
    else
        redirect_message('erro-alteracao', 'err');

} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {

    if (isset($_GET['err'])) {
        switch ($_GET['err']) {
            case 'dados-insuficientes':
                $errmsg = 'Dados insuficientes.';
                break;
            case 'senhas-nao-conferem':
                $errmsg = 'Senha e confirmação de senha não conferem.';
                break;
            case 'servico-indisponivel':
                $errmsg = 'Serviço indisponível, tente novamente mais tarde.';
                break;
            case 'senha-invalida':
                $errmsg = 'Senha inválida.';
                break;
            case 'erro-alteracao':
                $errmsg = 'Erro desconhecido durante processo de alteração.';
                break;
        }

    } else if (isset($_GET['msg'])) {

        switch ($_GET['msg']) {
            case 'senha-alterada':
                $sucmsg = 'Senha alterada com sucesso.';
                break;
        }

    }
}
?>
<!DOCTYPE html>

<html>
    <head>
        <title>LDAP: Alterar senha</title>
	<meta http-equiv="content-type" content="text/html;charset=<?php echo PAGE_CHARSET; ?>" />
        <style type="text/css">
            * { position:relative; font-family:Verdana,Sans; font-size:1em; letter-spacing:-0.04em }
            body { margin:40px 20px; font-size:0.8em }
            h1 { font-size:2em; color:#2F462B; margin:20px 0 20px 0 }
            h2 { font-size:1.2em; margin-top:10px; margin-bottom:20px }
            form { padding-top:10px }
            p.error { color:#cf0000; font-weight:bold; padding-top:10px }
            p.success { color:#379527; font-weight:bold; padding-top:10px }
            label{ font-weight:bold; display:block; color:#666 }
            input { font-size:1.5em; padding:2px; margin-bottom:10px; margin:5px 1px 1px 1px; border:solid 1px #ccc; color:#000 }
            input:focus { border:solid 2px #769870; margin:4px 0 0 0; color:#000 }
            .button, .button:focus { border-radius:5px; background-color:#2F462B; color:#FFFFFF; border:0; padding:5px 8px; font-size:0.9em; font-weight:bold; margin:30px 10px 0 0; cursor:pointer }
        </style>
    </head>
    <body>
        <h1>LDAP: Alterar senha</h1>
        <?php if (isset($errmsg)): ?>
        <p class="error"><?php echo $errmsg; ?></p>
        <?php elseif (isset($sucmsg)): ?>
        <p class="success"><?php echo $sucmsg; ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo "{$_SERVER['PHP_SELF']}?{$_SERVER['QUERY_STRING']}"; ?>">

            <div class="basic-options">
                <h2>Opções de usuário</h2>

                <p><label for="login">Login:</label>
                    <input type="text" name="login" id="login" /></p>

                <p><label for="oldpass">Senha antiga:</label>
                    <input type="password" name="oldpass" id="oldpass" /></p>

                <p><label for="newpass">Senha nova:</label>
                    <input type="password" name="newpass" id="newpass" /></p>

                <p><label for="cnewpass">Confirme a senha nova:</label>
                    <input type="password" name="cnewpass" id="cnewpass" /></p>
            </div>

            <?php if (ADMIN_ENABLED && isset($_GET['type']) && $_GET['type'] == 'advanced'): ?>
            <br />
            <div class="advanced-options">
                <h2>Opções avançadas</h2>

                <p><label for="admindn">Admin DN:</label>
                    <input type="text" name="admindn" id="admindn" /></p>

                <p><label for="adminpass">Admin senha:</label>
                    <input type="password" name="adminpass" id="adminpass" /></p>
            </div>
            <?php endif; ?>

            <p><input type="submit" class="button" value="Enviar">
    	    	<input type="reset" class="button" value="Limpar"></p>
        </form>
    </body>
</html>
