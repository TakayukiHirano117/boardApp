<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Board App</title>
<link rel="stylesheet" href="stylesheets/app.css">
</head>
<body>
<?php
// エスケープ処理の関数を読み込む
require_once __DIR__ . "/lib/escape.php";

// データベースに接続
$dsn = 'データベース名;ホスト名';
$user = 'ユーザー名';
$password = 'パスワード';
$pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));

// データベースを作成
$createDatabaseSql = <<<EOT
CREATE DATABASE IF NOT EXISTS データベース名;
EOT;

$result = $pdo->query($createDatabaseSql);

// テーブルが無かったら作成
$createTableSql = <<<EOT
CREATE TABLE  IF NOT EXISTS データベース名.board (
    id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(32),
    comment VARCHAR(1000),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    post_pass VARCHAR(255) NOT NULL
) DEFAULT CHARACTER SET=utf8mb4;
EOT;

$result = $pdo->query($createTableSql);

// 新規投稿時の処理 リロードしたときに二重投稿されるのを直す
if(!empty($_POST["name"]) && !empty($_POST["comment"]) && !empty($_POST["post_pass"]) && empty($_POST["flag"])) {
    $name = $_POST["name"];
    $comment = $_POST["comment"];
    // $date = date("Y/m/d H:i:s");
    $post_pass = password_hash($_POST["post_pass"], PASSWORD_DEFAULT);

    $sql = $pdo->prepare("INSERT INTO board (name, comment, post_pass) VALUES (:name, :comment, :post_pass)");
    $sql -> bindParam(':name', $name, PDO::PARAM_STR);
    $sql -> bindParam(':comment', $comment, PDO::PARAM_STR);
    $sql -> bindParam(':post_pass', $post_pass, PDO::PARAM_STR);
    $sql -> execute();
    
// 削除機能の実装
} elseif (!empty($_POST["num_delete"]) && !empty($_POST["delete_pass"])) {
    $num_delete = $_POST["num_delete"];
    $delete_pass = $_POST["delete_pass"];

    // データベースから投稿番号とパスワードを取ってくる、(パスワードは照合する)
    $selectRecordSql = "SELECT * FROM board";
    $result = $pdo -> query($selectRecordSql);
    $results = $result -> fetchAll();

    foreach($results as $row) {
        if($row["id"] === $num_delete && password_verify($delete_pass, $row['post_pass']) === true) {
        $deleteRecordSql = "delete from board where id=:id";
        $result = $pdo->prepare($deleteRecordSql);
        $result->bindParam(":id", $num_delete, PDO::PARAM_INT);
        $result->execute();
    }
}

// 編集機能の実装
} elseif (!empty($_POST["num_edit"]) && !empty($_POST["edit_pass"])) {
    $num_edit = $_POST["num_edit"];
    $edit_pass = $_POST["edit_pass"];

    $selectRecordSql = "SELECT * FROM board";
    $result = $pdo -> query($selectRecordSql);
    $results = $result -> fetchAll();

    foreach($results as $row) {
        if($row["id"] === $num_edit && password_verify($edit_pass, $row["post_pass"]) === true) {
            $edit_number = $row["id"];
            $edit_name = $row["name"];
            $edit_comment = $row["comment"];
            break;
        }
    }
// 編集フォームで取得したデータを投稿ドームに表示、再度投稿すると
// 変更が反映される
} elseif (!empty($_POST["flag"]) && !empty($_POST["name"]) && !empty($_POST["comment"]) && !empty($_POST["post_pass"])) {
    $update_number = $_POST["flag"];
    $update_name = $_POST["name"];
    $update_comment = $_POST["comment"];
    $update_pass = $_POST["post_pass"];
    
    $updateRecordSql = "UPDATE board SET name=:name, comment=:comment, post_pass=:post_pass WHERE id=:id";
    $result = $pdo->prepare($updateRecordSql);
    $result->bindParam(":name", $update_name, PDO::PARAM_STR);
    $result->bindParam(":comment", $update_comment, PDO::PARAM_STR);
    $result->bindParam(":id", $update_number, PDO::PARAM_INT);
    $result->bindParam(":post_pass", $update_pass, PDO::PARAM_INT);
    $result->execute();
}

?>

<div class="container">
    <h1 class="h2 text-dark mt-4 mb-4">【投稿送信フォーム】</h1>
    <form action="" method="post">
        <input type="hidden" name="flag" value="<?= $edit_number ?? ''; ?>">
        名前 : <input type="text" name="name" placeholder="名前を入力してください" value="<?= escape($edit_name ?? ''); ?>" class="mb-3 form-control"><br>
        コメント : <input type="text" name="comment" placeholder="コメントを入力してください" value="<?= escape($edit_comment ?? ''); ?>" class="mb-3 form-control"><br>
        パスワード : <input type="password" name="post_pass" placeholder="パスワードを入力してください" class="form-control"><br>
        <input type="submit" name="submit" class=" btn btn-primary mb-4">
    </form>
    <h1 class="h2">【削除フォーム】</h1>
    <form action="" method="post">
        投稿番号 : <input type="number" name="num_delete" placeholder="削除対象番号" class="mb-3 form-control"><br>
        パスワード : <input type="password" name="delete_pass" class="form-control"><br>
        <input type="submit" name="delete" value="削除" class="btn btn-danger mb-4">
    </form>
    <h1 class="h2">【編集フォーム】</h1>
    <form action="" method="post">
        投稿番号 : <input type="number" name="num_edit" placeholder="編集対象番号" class="mb-3 form-control"><br>
        パスワード : <input type="password" name="edit_pass" class="form-control"><br>
        <input type="submit" name="edit" value="編集" class="btn btn-success mb-4">
    </form>
    <h1 class="h2 text-dark mt-4 mb-4">【投稿一覧】</h1>
</div>

<?php

function validate() {
    $errors = [];

    if(!strlen($_POST["name"])) {
        $errors['name'] = '名前を入力してください';
    } elseif (strlen($_POST["name"]) > 32) {
        $errors['name'] = '名前は32文字以内で入力してください';
    }

    if(!strlen($_POST['comment'])) {
        $errors['comment'] = 'コメントを入力してください';
    } elseif (strlen($_POST['comment']) > 1000) {
        $errors['comment'] = 'コメントは1000文字以内で入力してください';
    }

    return $errors;
}

// function dbConnect() {
//     $dsn = 'mysql:dbname=tb240038db;host=localhost';
//     $user = 'tb-240038';
//     $password = '6y7gSTA9uZ';

//     try {
//         $pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
//     } catch (PDOException $e) {
//         echo 'Connection failed: ' . $e->getMessage() . "-" . $e->getLine() . PHP_EOL;
//     }
    
// }

$sql = "SELECT * FROM board";
$result = $pdo -> query($sql);
$results = $result -> fetchAll();
?>
<main class="container">
    <?php if (count($results) > 0) : ?>
        <?php foreach ($results as $result) : ?>
            <section class="card shadow-sm mb-4">
                <div class="card-body">

                        <?php echo escape($result['id']); ?>

                    <h2 class="card-title h4  text-dark mb-3">
                        <?php echo escape($result['name']); ?>
                    </h2>
                    <p>
                        <?php echo nl2br(escape($result['comment']), false) ?>
                    </p>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else : ?>
        <div>何も投稿されていません</div>
    <?php endif; ?>
</main>

</body>
</html>
