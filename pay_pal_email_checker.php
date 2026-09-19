<?php
// pay_pal_email_checker.php - PayPal Email Checker (UI/UX Enhanced)
error_reporting(E_ALL); // عرض جميع أخطاء PHP
ini_set('display_errors', 1);
set_time_limit(0); // يسمح بالعمل لفترة مفتوحة

// ==========================================
// ⚙️ Configuration
// ==========================================
$config = [
    'proxy_file' => 'proxies.txt',       // الملف الذي يحتوي على البروكسيات
    'user_agents' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ],
    'delay' => 2,                        // الثواني بين كل طلب فحص
    'timeout' => 15,                     // مهلة cURL (بالثواني)
    'output_file' => 'results.txt',      // ملف سجل النتائج
];

// ==========================================
// 💾 Initialization (Loading Data)
// ==========================================
$proxies = [];
if (file_exists($config['proxy_file'])) {
    $proxies = file($config['proxy_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $proxies = array_filter($proxies, function($proxy) {
        return !empty(trim($proxy));
    });
}

$userAgents = $config['user_agents'];
$results = []; // مصفوفة لتخزين النتائج
$totalChecked = 0;

// ==========================================
// 🎯 Core Logic (Processing)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emails = [];
    if (!empty($_POST['emails'])) {
        // المدخل من مربع النص (نص متعدد الأسطر)
        $emails = explode("\n", str_replace("\r", "", $_POST['emails']));
    } elseif (!empty($_FILES['email_file']['tmp_name'])) {
        // المدخل من رفع الملف
        $emails = file($_FILES['email_file']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    // تنظيف وتصفية الإيميلات للتأكد من أنها صيغتها صحيحة
    $emails = array_filter($emails, function($email) {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    });

    if (empty($emails)) {
        $results[] = ['email' => 'N/A', 'status' => '⚠️', 'message' => 'لا توجد إيميلات صالحة مقدمة.', 'proxy' => 'None'];
    } else {
        // فتح ملف السجل للكتابة
        $logHandle = fopen($config['output_file'], 'a');
        if (!$logHandle) {
            $results[] = ['email' => 'N/A', 'status' => '❌', 'message' => 'فشل فتح ملف السجل.', 'proxy' => 'None'];
        } else {
            // معالجة كل إيميل
            foreach ($emails as $email) {
                $email = trim($email);
                
                // اختيار عشوائي للبروكسي والـ User Agent
                $proxy = !empty($proxies) ? $proxies[array_rand($proxies)] : 'None';
                $userAgent = $userAgents[array_rand($userAgents)];

                // تنفيذ الفحص
                $checkResult = checkPayPalEmail($email, $proxy, $userAgent, $config['timeout']);
                
                // جمع النتيجة
                $results[] = [
                    'email' => $email,
                    'status' => $checkResult['status'],
                    'message' => $checkResult['message'],
                    'proxy' => $proxy
                ];

                // كتابة السجل في ملف النتائج
                $logEntry = sprintf(
                    "[%s] Email: %s | Status: %s | Proxy: %s\n",
                    date('Y-m-d H:i:s'),
                    $email,
                    $checkResult['status'],
                    $proxy
                );
                fwrite($logHandle, $logEntry);

                // الانتظار
                sleep($config['delay']);
                $totalChecked++;
            }
            fclose($logHandle);
        }
    }
}

// ==========================================
// 💡 Output (Displaying Results)
// ==========================================
$log_content = file_exists($config['output_file']) 
    ? htmlspecialchars(file_get_contents($config['output_file'])) 
    : "لا توجد نتائج مسجلة حتى الآن.";

// حساب إحصائيات النتائج
$counts = [
    'Registered' => 0,
    'Not Registered' => 0,
    'Error' => 0,
    'Unknown Response' => 0,
    'N/A' => 0,
    'Total' => count($results)
];

foreach ($results as $result) {
    $status = $result['status'];
    if (strpos($status, 'Registered') !== false) $counts['Registered']++;
    elseif (strpos($status, 'Not Registered') !== false) $counts['Not Registered']++;
    elseif (strpos($status, 'Error') !== false || strpos($status, 'HTTP') !== false) $counts['Error']++;
    elseif ($status === 'Unknown Response') $counts['Unknown Response']++;
    else $counts['N/A']++;
}

// حساب نسبة التقدم إذا كنا نعرض النتائج في واجهة
$progressPercentage = ($counts['Total'] > 0) ? round(($totalChecked / $counts['Total']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 مدقق إيميلات باي بال (PayPal Email Checker)</title>
    <style>
        /* Google Font (optional but recommended) */
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');
        
        :root {
            --primary-blue: #0070ba;
            --light-blue: #e0f2ff;
            --dark-blue: #005f9e;
            --success-green: #4CAF50;
            --warning-orange: #ff9800;
            --error-red: #f44336;
            --bg-light: #f8f9fa;
            --text-dark: #343a40;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
            padding: 20px;
            margin: 0;
        }

        /* Main Container */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            display: grid;
            grid-template-columns: 1fr 2fr; /* تقسيم الشاشة: إدخال/إعدادات ونتائج */
            gap: 30px;
        }

        /* Headings and Titles */
        h1 {
            color: var(--primary-blue);
            text-align: center;
            grid-column: 1 / -1; /* يمتد على كامل عرض الشاشة */
            margin-bottom: 30px;
        }
        h2 {
            color: var(--dark-blue);
            border-bottom: 2px solid var(--light-blue);
            padding-bottom: 10px;
            margin-top: 25px;
        }

        /* Input Form Section (Left Side) */
        .input-area label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        textarea, input[type="file"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        textarea:focus, input[type="file"]:focus {
            border-color: var(--primary-blue);
            outline: none;
        }

        /* Button Styling */
        button {
            width: 100%;
            padding: 12px 15px;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            transition: background 0.3s, transform 0.1s;
            margin-top: 10px;
        }
        button:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
        }

        /* Settings/Config Box */
        .config-box {
            background: var(--light-blue);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dotted #cce5ff;
        }
        .setting-row:last-child {
            border-bottom: none;
        }
        .setting-row span {
            font-weight: 600;
        }
        .setting-row input[type="number"], .setting-row code {
            padding: 5px 8px;
            border: 1px solid #adb5bd;
            border-radius: 4px;
            background: white;
        }

        /* Results Section (Right Side) */
        .results-area {
            /* شريط التقدم */
            margin-bottom: 25px;
        }
        .progress-container {
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
        }
        .progress-bar {
            height: 100%;
            width: 0%;
            background-color: var(--primary-blue);
            transition: width 0.5s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .progress-text {
            font-weight: 700;
            color: white;
            font-size: 14px;
            padding: 0 10px;
            transition: opacity 0.3s;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-card {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 24px;
            line-height: 1.1;
        }
        .stat-card p {
            margin-top: 5px;
            font-size: 14px;
            font-weight: 500;
        }

        /* Colors for Stats */
        .stat-registered { background-color: #e6ffe9; border-left: 5px solid var(--success-green); }
        .stat-not-registered { background-color: #fff8e0; border-left: 5px solid var(--warning-orange); }
        .stat-error { background-color: #ffebe9; border-left: 5px solid var(--error-red); }
        .stat-unknown { background-color: #eef2ff; border-left: 5px solid var(--primary-blue); }
        .stat-total { background-color: #d0e9ff; border-left: 5px solid var(--dark-blue); }

        /* Results Display */
        .results-list {
            max-height: 550px;
            overflow-y: auto; /* التمرير عمودياً */
            padding-right: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background-color: #fafafa;
        }
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .result-item:hover {
            background-color: #f0f8ff;
        }
        .result-info {
            flex-grow: 1;
        }
        .result-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            text-align: center;
            min-width: 120px;
            color: white;
            font-size: 14px;
        }
        .status-registered { background-color: var(--success-green); }
        .status-not-registered { background-color: var(--warning-orange); }
        .status-error { background-color: var(--error-red); }
        .status-unknown { background-color: var(--primary-blue); }
        .status-n-a { background-color: #6c757d; }
        
        .result-item:last-child {
            border-bottom: none;
        }

        /* Footer/Download Link */
        .download-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
        }
        .download-link a {
            background: var(--primary-blue);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 18px;
            transition: background 0.3s;
        }
        .download-link a:hover {
            background: var(--dark-blue);
            transform: scale(1.02);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .container {
                grid-template-columns: 1fr; /* يصبح عموداً واحداً على الشاشات الصغيرة */
                padding: 20px;
            }
            .results-list {
                max-height: 450px;
            }
        }
        @media (max-width: 576px) {
            h1 {
                font-size: 28px;
            }
            button {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- ================================== -->
        <!-- ⬅️ القسم الأيسر: الإدخال والإعدادات -->
        <!-- ================================== -->
        <div class="input-area">
            <h2>📥 مدخلات الفحص</h2>
            <form method="post" enctype="multipart/form-data">
                
                <!-- اختيار الإيميلات -->
                <label for="emails">أدخل الإيميلات (سطر واحد لكل إيميل):</label>
                <textarea id="emails" name="emails" rows="12" placeholder="مثال: first.user@domain.com&#10;second.user@domain.com&#10;third.user@company.org"></textarea>

                <!-- رفع الملف -->
                <label for="email_file">أو ارفع قائمة الإيميلات (.txt):</label>
                <input type="file" id="email_file" name="email_file" accept=".txt">

                <button type="submit" id="checkButton">✅ ابدأ الفحص</button>
            </form>
        </div>
        
        <!-- ================================== -->
        <!-- ➡️ القسم الأيمن: النتائج والمؤشرات -->
        <!-- ================================== -->
        <div class="results-area">
            <h2>📊 حالة العملية</h2>
            
            <!-- شريط التقدم -->
            <div class="progress-container">
                <div class="progress-bar" style="width: <?php echo $progressPercentage; ?>%;">
                    <span class="progress-text"><?php echo $progressPercentage; ?>% مكتمل</span>
                </div>
            </div>
            <p style="text-align: center; font-size: 14px; margin-top: 5px;">
                <?php echo $totalChecked > 0 ? $totalChecked : '0'; ?> إيميل تم فحصه
            </p>

            <!-- إحصائيات النتائج (Cards) -->
            <div class="stats-grid">
                <div class="stat-card stat-registered">
                    <h3>✅</h3>
                    <p><?php echo $counts['Registered']; ?></p>
                    <p>مسجل (Registered)</p>
                </div>
                <div class="stat-card stat-not-registered">
                    <h3>❌</h3>
                    <p><?php echo $counts['Not Registered']; ?></p>
                    <p>غير مسجل (Not Registered)</p>
                </div>
                <div class="stat-card stat-error">
                    <h3>⚠️</h3>
                    <p><?php echo $counts['Error']; ?></p>
                    <p>خطأ في الطلب (Error)</p>
                </div>
                <div class="stat-card stat-total">
                    <h3><?php echo $counts['Total']; ?></h3>
                    <p>الإجمالي الكلي</p>
                </div>
                <!-- اختياري: عرض النتائج الأخرى -->
                 <div class="stat-card stat-unknown">
                    <h3>❓</h3>
                    <p><?php echo $counts['Unknown Response']; ?></p>
                    <p>استجابة مجهولة</p>
                </div>
                <div class="stat-card stat-n-a">
                    <h3>N/A</h3>
                    <p><?php echo $counts['N/A']; ?></p>
                    <p>لم يتم الفحص</p>
                </div>
            </div>

            <!-- قائمة النتائج التفصيلية -->
            <h2 style="margin-top: 40px;">📜 النتائج التفصيلية</h2>
            <div class="results-list">
                <?php if (!empty($results)): ?>
                    <?php foreach ($results as $result): ?>
                        <div class="result-item">
                            <div class="result-info">
                                <strong><?php echo htmlspecialchars($result['email']); ?></strong>
                                <br><small>البروكسي المستخدم: <code><?php echo htmlspecialchars($result['proxy']); ?></code></small>
                                <br><small><?php echo htmlspecialchars($result['message']); ?></small>
                            </div>
                            <div class="result-status-badge status-<?php echo strtolower(str_replace(' ', '-', $result['status'])); ?>">
                                <?php echo htmlspecialchars($result['status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #6c757d;">لم يتم تشغيل الفحص بعد. الرجاء إدخال إيميلات والضغط على زر "ابدأ الفحص".</p>
                <?php endif; ?>
            </div>
            
            <!-- رابط التحميل -->
            <div class="download-link">
                <a href="<?php echo $config['output_file']; ?>" download>⬇️ تحميل جميع النتائج (.txt)</a>
            </div>
        </div>
    </div>
</body>
</html>
