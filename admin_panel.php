<?php
/**
 * =================================================================
 * AfghanCodeAI - Secure Admin Panel (Complete & Final Version)
 * =================================================================
 * This is a self-contained panel for managing global settings and database.
 * SECURITY: Access is protected by the ADMIN_SECRET_KEY defined in config.php.
 */

// Load the main configuration file which now contains the secret key.
require_once __DIR__ . '/config.php';

// --- Security Gatekeeper ---
if (!defined('ADMIN_SECRET_KEY') || !isset($_GET['secret']) || $_GET['secret'] !== ADMIN_SECRET_KEY) {
    header('HTTP/1.1 403 Forbidden');
    die('Access Denied. Please provide the correct secret key in the URL (e.g., ?secret=YOUR_KEY).');
}

// --- Backend Logic (API Handling) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $pdo = null;
    try {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s', DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
        $pdo = new \PDO($dsn, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }

    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Invalid action.'];

    if ($action === 'get_rules') {
        $stmt = $pdo->query("SELECT id, rule, created_at FROM global_settings ORDER BY id DESC");
        $rules = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $response = ['success' => true, 'rules' => $rules];
    } 
    elseif ($action === 'delete_rule' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM global_settings WHERE id = ?");
        if ($stmt->execute([$_POST['id']])) {
            $response = ['success' => true, 'message' => 'Rule deleted successfully.'];
        } else {
            $response['message'] = 'Failed to delete rule.';
        }
    }
    elseif ($action === 'nuke_database') {
        try {
            $pdo->exec("TRUNCATE TABLE chat_history, global_settings RESTART IDENTITY;");
            $response = ['success' => true, 'message' => 'All histories and global settings have been wiped from the database.'];
        } catch (\PDOException $e) {
            $response['message'] = 'Database wipe failed: ' . $e->getMessage();
        }
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اتاق کنترل AfghanCodeAI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background-color: #030712; color: #d1d5db; overflow-x: hidden; }
        #webgl-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; opacity: 0.15; }
        .glass-card { background: rgba(17, 24, 39, 0.4); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); border: 1px solid rgba(55, 65, 81, 0.2); }
        .btn-delete { background-color: #991b1b; color: white; transition: all 0.2s; } .btn-delete:hover { background-color: #b91c1c; transform: scale(1.1); }
        .btn-nuke { background-color: #dc2626; color: white; transition: all 0.2s; } .btn-nuke:hover { background-color: #ef4444; } .btn-nuke:disabled { background-color: #4b5563; cursor: not-allowed; }
        .modal-overlay { background-color: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
    </style>
</head>
<body class="antialiased">
    <canvas id="webgl-canvas"></canvas>
    <div class="container mx-auto p-4 md:p-8">
        <header class="text-center py-8">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-red-500 to-orange-400">اتاق کنترل AfghanCodeAI</h1>
            <p class="mt-2 text-gray-400">ابزارهای مدیریتی برای ادمین سیستم</p>
        </header>

        <main class="max-w-4xl mx-auto space-y-8">
            <!-- Global Rules Manager -->
            <section id="rules-manager" class="glass-card rounded-2xl p-6">
                <h2 class="text-2xl font-bold mb-4 text-white">مدیریت دستورات عمومی</h2>
                <div id="rules-list" class="space-y-3 max-h-96 overflow-y-auto pr-2">
                    <p id="loading-text" class="text-center text-gray-400 p-4">در حال بارگذاری قوانین...</p>
                </div>
            </section>

            <!-- Danger Zone -->
            <section id="danger-zone" class="glass-card rounded-2xl p-6 border-2 border-red-500/50">
                <h2 class="text-2xl font-bold mb-4 text-red-400">منطقه خطر!</h2>
                <div class="space-y-4">
                    <p class="text-red-300">عملیات در این بخش غیرقابل بازگشت هستند. با احتیاط کامل ادامه دهید.</p>
                    <div>
                        <label for="nuke-confirm-text" class="block mb-2 font-semibold text-gray-200">برای فعال کردن دکمه، عبارت "DELETE ALL DATA" را تایپ کنید:</label>
                        <input type="text" id="nuke-confirm-text" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2 text-center text-white focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none" placeholder="DELETE ALL DATA">
                    </div>
                    <button id="nuke-btn" disabled class="w-full p-3 rounded-lg font-bold btn-nuke">حذف کامل دیتابیس (تاریخچه و قوانین)</button>
                </div>
            </section>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div id="modal" class="fixed inset-0 modal-overlay z-50 flex items-center justify-center hidden">
        <div class="glass-card rounded-lg p-6 w-11/12 max-w-sm text-center">
            <h3 id="modal-title" class="text-xl font-bold mb-4 text-white"></h3>
            <p id="modal-text" class="mb-6 text-gray-300"></p>
            <div class="flex justify-center gap-4">
                <button id="modal-cancel" class="py-2 px-6 rounded-lg bg-gray-600 hover:bg-gray-500 text-white font-semibold">لغو</button>
                <button id="modal-confirm" class="py-2 px-6 rounded-lg bg-red-600 hover:bg-red-500 text-white font-bold">تایید و اجرا</button>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        const secretKey = new URLSearchParams(window.location.search).get('secret');
        let currentAction = null;
        let currentId = null;

        function apiRequest(data, callback) {
            $.post("?secret=" + secretKey, data, callback, 'json').fail(function() {
                alert('خطایی در ارتباط با سرور رخ داد.');
            });
        }

        function loadRules() {
            $('#loading-text').show();
            $('#rules-list').empty().append($('#loading-text'));
            apiRequest({ action: 'get_rules' }, function(response) {
                $('#loading-text').hide();
                if (response.success && response.rules.length > 0) {
                    response.rules.forEach(function(rule) {
                        const ruleHtml = `<div class="bg-gray-800/50 p-4 rounded-lg flex justify-between items-center animate-fade-in">
                                <p class="text-gray-300 flex-1"><code>${escapeHtml(rule.rule)}</code></p>
                                <button data-id="${rule.id}" class="btn-delete p-2 rounded-md"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clip-rule="evenodd" /></svg></button>
                            </div>`;
                        $('#rules-list').append(ruleHtml);
                    });
                } else if (response.success) {
                    $('#rules-list').html('<p class="text-center text-gray-500 p-4">هیچ قانون عمومی ثبت نشده است.</p>');
                } else {
                    $('#rules-list').html(`<p class="text-center text-red-400 p-4">خطا در بارگذاری قوانین: ${response.message}</p>`);
                }
            });
        }

        function showModal(title, text, action, id = null) {
            $('#modal-title').text(title);
            $('#modal-text').text(text);
            currentAction = action;
            currentId = id;
            $('#modal').removeClass('hidden').hide().fadeIn(200);
        }

        $('#rules-list').on('click', '.btn-delete', function() {
            const ruleId = $(this).data('id');
            showModal('حذف قانون عمومی', 'آیا از حذف این قانون برای همیشه مطمئن هستید؟', 'delete_rule', ruleId);
        });

        $('#nuke-confirm-text').on('input', function() {
            $('#nuke-btn').prop('disabled', $(this).val() !== 'DELETE ALL DATA');
        });

        $('#nuke-btn').on('click', function() {
            if ($(this).is(':disabled')) return;
            showModal('!!! حذف کامل دیتابیس !!!', 'این عمل غیرقابل بازگشت است و تمام تاریخچه‌ها و قوانین را برای همیشه پاک می‌کند. آیا کاملاً مطمئن هستید؟', 'nuke_database');
        });

        $('#modal-cancel').on('click', () => $('#modal').fadeOut(200));
        $('#modal-confirm').on('click', function() {
            if (!currentAction) return;
            const data = { action: currentAction, id: currentId };
            apiRequest(data, function(response) {
                alert(response.message);
                if (response.success) {
                    loadRules();
                    if (currentAction === 'nuke_database') {
                        $('#nuke-confirm-text').val('');
                        $('#nuke-btn').prop('disabled', true);
                    }
                }
            });
            $('#modal').fadeOut(200);
        });

        function escapeHtml(text) {
            return $('<div/>').text(text).html();
        }

        // --- FULL WebGL Background ---
        let scene, camera, renderer, particles, mouseX = 0, mouseY = 0;
        const windowHalfX = window.innerWidth / 2;
        const windowHalfY = window.innerHeight / 2;

        function initWebGL() {
            const canvas = document.getElementById('webgl-canvas');
            scene = new THREE.Scene();
            camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 1, 3000);
            camera.position.z = 1000;
            renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
            renderer.setSize(window.innerWidth, window.innerHeight);

            const geometry = new THREE.BufferGeometry();
            const vertices = [];
            const sprite = new THREE.TextureLoader().load( 'https://threejs.org/examples/textures/sprites/disc.png' );

            for ( let i = 0; i < 5000; i ++ ) {
                vertices.push( (Math.random() * 2000 - 1000), (Math.random() * 2000 - 1000), (Math.random() * 2000 - 1000) );
            }
            geometry.setAttribute( 'position', new THREE.Float32BufferAttribute( vertices, 3 ) );

            const material = new THREE.PointsMaterial({ size: 10, sizeAttenuation: true, map: sprite, alphaTest: 0.5, transparent: true });
            material.color.setHSL(0.6, 0.7, 0.5); // A nice violet/blue hue

            particles = new THREE.Points( geometry, material );
            scene.add( particles );

            window.addEventListener('resize', onWindowResize, false);
            document.addEventListener('mousemove', onDocumentMouseMove, false);
        }

        function onWindowResize() {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        }

        function onDocumentMouseMove(event) {
            mouseX = event.clientX - windowHalfX;
            mouseY = event.clientY - windowHalfY;
        }

        function animate() {
            requestAnimationFrame(animate);
            const time = Date.now() * 0.00005;
            camera.position.x += (mouseX - camera.position.x) * 0.05;
            camera.position.y += (-mouseY - camera.position.y) * 0.05;
            camera.lookAt(scene.position);
            const h = (360 * (0.6 + time) % 360) / 360;
            particles.material.color.setHSL(h, 0.7, 0.5);
            particles.rotation.x += 0.001;
            particles.rotation.y += 0.002;
            renderer.render(scene, camera);
        }
        
        initWebGL();
        animate();
        loadRules();
    });
    </script>
</body>
</html>
