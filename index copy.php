<?php
// 啟用錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 包含資料庫連接資訊
include "../inc/dbinfo.inc";

// 定義所有需要的函數

/* 檢查表是否存在 */
function TableExists($tableName, $pdo, $dbName) {
    $stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?");
    $stmt->execute([$tableName, $dbName]);
    return $stmt->rowCount() > 0;
}

/* 檢查表是否存在，如果不存在則創建；如果存在，檢查並添加缺失的欄位 */
function VerifyContactsTable($pdo, $dbName) {
    if (!TableExists("CONTACTS", $pdo, $dbName)) {
        try {
            $createQuery = "CREATE TABLE CONTACTS (
                ID int(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                NAME VARCHAR(45) NOT NULL,
                ADDRESS VARCHAR(90) NOT NULL,
                PHONE VARCHAR(20),
                EMAIL VARCHAR(100)
            )";
            $pdo->exec($createQuery);
            // echo "<p>成功創建 CONTACTS 表。</p>"; // 可選調試信息
        } catch (PDOException $e) {
            echo("<div class='alert alert-danger'>創建表時出錯: " . $e->getMessage() . "</div>");
        }
    } else {
        // 檢查並添加缺失的欄位
        $required_columns = ['PHONE', 'EMAIL'];
        foreach ($required_columns as $column) {
            $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute(['CONTACTS', $column]);
            if ($stmt->rowCount() == 0) {
                // 添加缺失的欄位
                if ($column == 'PHONE') {
                    $alter_query = "ALTER TABLE CONTACTS ADD COLUMN PHONE VARCHAR(20)";
                } elseif ($column == 'EMAIL') {
                    $alter_query = "ALTER TABLE CONTACTS ADD COLUMN EMAIL VARCHAR(100)";
                }
                try {
                    $pdo->exec($alter_query);
                    echo("<div class='alert alert-success'>成功添加 '$column' 欄位到 CONTACTS 表。</div>");
                } catch (PDOException $e) {
                    echo("<div class='alert alert-danger'>添加 '$column' 欄位時出錯: " . $e->getMessage() . "</div>");
                }
            }
        }
    }
}

/* 添加聯絡人 */
function AddContact($pdo, $name, $address, $phone, $email) {
    try {
        $stmt = $pdo->prepare("INSERT INTO CONTACTS (NAME, ADDRESS, PHONE, EMAIL) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $address, $phone, $email]);
        echo("<div class='alert alert-success'>成功添加聯絡人！</div>");
    } catch (PDOException $e) {
        echo("<div class='alert alert-danger'>添加聯絡人資料時出錯: " . $e->getMessage() . "</div>");
    }
}

/* 更新聯絡人 */
function UpdateContact($pdo, $id, $name, $address, $phone, $email) {
    try {
        $stmt = $pdo->prepare("UPDATE CONTACTS SET NAME = ?, ADDRESS = ?, PHONE = ?, EMAIL = ? WHERE ID = ?");
        $stmt->execute([$name, $address, $phone, $email, $id]);
        echo("<div class='alert alert-success'>成功更新聯絡人！</div>");
    } catch (PDOException $e) {
        echo("<div class='alert alert-danger'>更新聯絡人資料時出錯: " . $e->getMessage() . "</div>");
    }
}

/* 刪除聯絡人 */
function DeleteContact($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM CONTACTS WHERE ID = ?");
        $stmt->execute([$id]);
        echo("<div class='alert alert-success'>成功刪除聯絡人！</div>");
    } catch (PDOException $e) {
        echo("<div class='alert alert-danger'>刪除聯絡人資料時出錯: " . $e->getMessage() . "</div>");
    }
}

/* 獲取單個聯絡人 */
function GetContact($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM CONTACTS WHERE ID = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo("<div class='alert alert-danger'>獲取聯絡人資料時出錯: " . $e->getMessage() . "</div>");
        return null;
    }
}

// 連接到資料庫
try {
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_DATABASE . ";charset=utf8";
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "<p>成功連接到資料庫。</p>"; // 可選調試信息
} catch (PDOException $e) {
    echo "<p>無法連接到資料庫: " . $e->getMessage() . "</p>";
    exit();
}

// 確保 CONTACTS 表存在
VerifyContactsTable($pdo, DB_DATABASE);

// 處理刪除操作
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    DeleteContact($pdo, $id);
}

// 處理編輯操作
$contact_id = '';
$contact_name = '';
$contact_address = '';
$contact_phone = '';
$contact_email = '';
$action = 'add';

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $contact_id = intval($_GET['id']);
    $contact = GetContact($pdo, $contact_id);
    if ($contact) {
        $contact_name = $contact['NAME'];
        $contact_address = $contact['ADDRESS'];
        $contact_phone = $contact['PHONE'];
        $contact_email = $contact['EMAIL'];
        $action = 'update';
    }
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action_post = $_POST['action'];
        $contact_name_post = trim($_POST['NAME']);
        $contact_address_post = trim($_POST['ADDRESS']);
        $contact_phone_post = trim($_POST['PHONE']);
        $contact_email_post = trim($_POST['EMAIL']);

        // 驗證電子郵件
        if (!empty($contact_email_post) && !filter_var($contact_email_post, FILTER_VALIDATE_EMAIL)) {
            echo("<div class='alert alert-danger'>請輸入有效的電子郵件地址。</div>");
        } else {
            // 驗證電話號碼（例如，只允許數字、+、-、空格）
            if (!empty($contact_phone_post) && !preg_match('/^[0-9+\-\s]+$/', $contact_phone_post)) {
                echo("<div class='alert alert-danger'>請輸入有效的電話號碼。</div>");
            } else {
                if ($action_post == 'add') {
                    if (strlen($contact_name_post) || strlen($contact_address_post)) {
                        AddContact($pdo, $contact_name_post, $contact_address_post, $contact_phone_post, $contact_email_post);
                    } else {
                        echo("<div class='alert alert-danger'>姓名或地址不能為空。</div>");
                    }
                } elseif ($action_post == 'update' && isset($_POST['id'])) {
                    $id_post = intval($_POST['id']);
                    UpdateContact($pdo, $id_post, $contact_name_post, $contact_address_post, $contact_phone_post, $contact_email_post);
                }
            }
        }
    }
}
?>
<!DOCTYPE HTML>
<!--
    Editorial by HTML5 UP
    html5up.net | @ajlkn
    Free for personal and commercial use under the CCA 3.0 license (html5up.net/license)
-->
<html>
    <head>
        <title>林恩佑的網站 - 聯絡人管理</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
        <link rel="stylesheet" href="assets/css/main.css" />
        <link type="image/png" sizes="96x96" rel="icon" href="assets/icons8-hard-working-96.png">
        
        <!-- 引入 Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-papxSnA3e21SnuoIMI4nXl+gGv+LO8lt9azlFQRVtX6l3YctEe1BmCjqGBxEYBIlEYGGRwQ+1GpNbtRFA7e9iw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    </head>

    <body class="is-preload">

        <!-- Wrapper -->
            <div id="wrapper">

                <!-- Main -->
                    <div id="main">
                        <div class="inner">

                            <!-- Header -->
                                <header id="header">
                                    <a href="/" class="logo"><strong>林恩佑的網站 - 聯絡人管理</strong></a>
                                    <ul class="icons">
                                        <li><a href="https://www.facebook.com/profile.php?id=100068804133842" class="icon brands fa-facebook-f alt"><span class="label">Facebook</span></a></li>
                                        <li><a href="https://www.youtube.com/@ianlin17698/" class="icon brands fa-youtube alt"><span class="label">YouTube</span></a></li>
                                        <li><a href="https://github.com/ian20040409/111-2_Web_Editorial" class="icon brands fa-github alt"><span class="label">GitHub</span></a></li>
                                        <div>
                                            <h2>
                                            <!-- 您可以在這裡添加其他標題或訊息 -->
                                            </h2>
                                        </div>
                                    </ul>
                                </header>

                            <!-- Content -->
                                <section>
                                    <header class="main">
                                        <h1>聯絡人管理</h1>
                                    </header>

                                    <!-- 添加/更新表單 -->
                                    <div class="box">
                                        <form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="POST">
                                            <input type="hidden" name="action" value="<?php echo $action; ?>" />
                                            <?php if ($action == 'update') { ?>
                                                <input type="hidden" name="id" value="<?php echo $contact_id; ?>" />
                                            <?php } ?>
                                            <div class="row gtr-uniform">
                                                <div class="col-6 col-12-small">
                                                    <input type="text" name="NAME" id="name" value="<?php echo htmlspecialchars($contact_name); ?>" placeholder="姓名" maxlength="45" required />
                                                </div>
                                                <div class="col-6 col-12-small">
                                                    <input type="text" name="ADDRESS" id="address" value="<?php echo htmlspecialchars($contact_address); ?>" placeholder="地址" maxlength="90" required />
                                                </div>
                                                <div class="col-6 col-12-small">
                                                    <input type="text" name="PHONE" id="phone" value="<?php echo htmlspecialchars($contact_phone); ?>" placeholder="電話" maxlength="20" />
                                                </div>
                                                <div class="col-6 col-12-small">
                                                    <input type="email" name="EMAIL" id="email" value="<?php echo htmlspecialchars($contact_email); ?>" placeholder="電子郵件" maxlength="100" />
                                                </div>
                                                <div class="col-12">
                                                    <ul class="actions">
                                                        <li>
                                                            <button type="submit" class="primary">
                                                                <?php echo ($action == 'update') ? '<i class="fas fa-save"></i> 更新聯絡人' : '<i class="fas fa-user-plus"></i> 添加聯絡人'; ?>
                                                            </button>
                                                        </li>
                                                        <?php if ($action == 'update') { ?>
                                                            <li><a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="button">取消</a></li>
                                                        <?php } ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- 搜尋表單 -->
                                    <div class="box">
                                        <form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="GET">
                                            <div class="row gtr-uniform">
                                                <div class="col-3 col-12-small">
                                                    <input type="text" name="search_name" placeholder="按姓名搜尋" value="<?php if (isset($_GET['search_name'])) echo htmlspecialchars($_GET['search_name']); ?>" />
                                                </div>
                                                <div class="col-3 col-12-small">
                                                    <input type="text" name="search_address" placeholder="按地址搜尋" value="<?php if (isset($_GET['search_address'])) echo htmlspecialchars($_GET['search_address']); ?>" />
                                                </div>
                                                <div class="col-3 col-12-small">
                                                    <input type="text" name="search_phone" placeholder="按電話搜尋" value="<?php if (isset($_GET['search_phone'])) echo htmlspecialchars($_GET['search_phone']); ?>" />
                                                </div>
                                                <div class="col-3 col-12-small">
                                                    <input type="email" name="search_email" placeholder="按電子郵件搜尋" value="<?php if (isset($_GET['search_email'])) echo htmlspecialchars($_GET['search_email']); ?>" />
                                                </div>
                                                <div class="col-12">
                                                    <ul class="actions">
													<li>
													<button type="submit" class="primary">
															<i class="fas fa-search"></i> 搜尋
														</button>
														</li>
                                                        <li><a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="button primary icon solid fa-redo">重置</a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- 顯示聯絡人列表 -->
                                    <div class="box">
                                        <header>
                                            <h2>聯絡人列表</h2>
                                        </header>
                                        <div class="table-wrapper">
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>姓名</th>
                                                        <th>地址</th>
                                                        <th>電話</th>
                                                        <th>電子郵件</th>
                                                        <th>操作</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                        /* 處理查詢 */
                                                        $search_conditions = array();
                                                        $params = array();

                                                        if (isset($_GET['search_name']) && $_GET['search_name'] != '') {
                                                            $search_conditions[] = "NAME LIKE ?";
                                                            $params[] = "%" . $_GET['search_name'] . "%";
                                                        }

                                                        if (isset($_GET['search_address']) && $_GET['search_address'] != '') {
                                                            $search_conditions[] = "ADDRESS LIKE ?";
                                                            $params[] = "%" . $_GET['search_address'] . "%";
                                                        }

                                                        if (isset($_GET['search_phone']) && $_GET['search_phone'] != '') {
                                                            $search_conditions[] = "PHONE LIKE ?";
                                                            $params[] = "%" . $_GET['search_phone'] . "%";
                                                        }

                                                        if (isset($_GET['search_email']) && $_GET['search_email'] != '') {
                                                            $search_conditions[] = "EMAIL LIKE ?";
                                                            $params[] = "%" . $_GET['search_email'] . "%";
                                                        }

                                                        $query = "SELECT * FROM CONTACTS";
                                                        if (count($search_conditions) > 0) {
                                                            $query .= " WHERE " . implode(' AND ', $search_conditions);
                                                        }

                                                        try {
                                                            $stmt = $pdo->prepare($query);
                                                            $stmt->execute($params);

                                                            while ($query_data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                                echo "<tr>";
                                                                echo "<td>" . htmlspecialchars($query_data['ID']) . "</td>";
                                                                echo "<td>" . htmlspecialchars($query_data['NAME']) . "</td>";
                                                                echo "<td>" . htmlspecialchars($query_data['ADDRESS']) . "</td>";
                                                                echo "<td>" . htmlspecialchars($query_data['PHONE']) . "</td>";
                                                                echo "<td>" . htmlspecialchars($query_data['EMAIL']) . "</td>";
                                                                echo "<td>";
                                                                echo "<a href='" . $_SERVER['SCRIPT_NAME'] . "?action=edit&id=" . $query_data['ID'] . "' class='button primary'><i class='fas fa-edit'></i> 編輯</a> ";
                                                                echo "<a href='" . $_SERVER['SCRIPT_NAME'] . "?action=delete&id=" . $query_data['ID'] . "' class='button delete' onclick=\"return confirm('確定要刪除此聯絡人嗎？');\"><i class='fas fa-trash-alt'></i> 刪除</a>";
                                                                
																echo "</td>";
                                                                echo "</tr>";
                                                            }
                                                        } catch (PDOException $e) {
                                                            echo("<tr><td colspan='6'><div class='alert alert-danger'>查詢聯絡人資料時出錯: " . $e->getMessage() . "</div></td></tr>");
                                                        }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <?php
                                        /* 清理資源 */
                                        $pdo = null;
                                    ?>

                                </section>

                        </div>
                    </div>

                <!-- Sidebar -->
                <!-- 如果需要側邊欄，可以在此處添加 -->

            </div>

        <!-- Scripts -->
        <script src="assets/js/jquery.min.js"></script>
        <script src="assets/js/browser.min.js"></script>
        <script src="assets/js/breakpoints.min.js"></script>
        <script src="assets/js/util.js"></script>
        <script src="assets/js/main.js"></script>

    </body>
</html>
