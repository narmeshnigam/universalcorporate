<?php
session_start();
require_once '../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) die('Database connection failed.');
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $q = trim($_POST['question'] ?? '');
        $a = trim($_POST['answer'] ?? '');
        if (!$q || !$a) { $message = 'Both question and answer are required.'; $messageType = 'error'; }
        else {
            $max = $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM faqs")->fetchColumn();
            $pdo->prepare("INSERT INTO faqs (question, answer, sort_order) VALUES (?,?,?)")->execute([$q, $a, $max + 1]);
            $message = 'FAQ added.'; $messageType = 'success';
        }
    }
    if ($action === 'update') {
        $id = (int)($_POST['faq_id'] ?? 0);
        $q = trim($_POST['question'] ?? '');
        $a = trim($_POST['answer'] ?? '');
        $pdo->prepare("UPDATE faqs SET question=?, answer=? WHERE id=?")->execute([$q, $a, $id]);
        $message = 'FAQ updated.'; $messageType = 'success';
    }
    if ($action === 'toggle') {
        $id = (int)($_POST['faq_id'] ?? 0);
        $pdo->prepare("UPDATE faqs SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        $message = 'Status updated.'; $messageType = 'success';
    }
    if ($action === 'delete') {
        $id = (int)($_POST['faq_id'] ?? 0);
        $pdo->prepare("DELETE FROM faqs WHERE id=?")->execute([$id]);
        $message = 'FAQ deleted.'; $messageType = 'success';
    }
    if ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $pdo->prepare("UPDATE faqs SET sort_order=? WHERE id=?");
            foreach ($order as $i => $id) $stmt->execute([$i + 1, (int)$id]);
        }
    }
}

$faqs = $pdo->query("SELECT * FROM faqs ORDER BY sort_order ASC")->fetchAll();
$pageTitle = 'FAQs - Admin Panel';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-header">
        <h1>FAQs</h1>
        <span class="badge"><?php echo count($faqs); ?> FAQs</span>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="margin-bottom:30px;">
        <h2>Add New FAQ</h2>
        <form method="POST" class="slider-form">
            <input type="hidden" name="action" value="add">
            <div class="form-grid" style="grid-template-columns:1fr;">
                <div class="form-group">
                    <label>Question <span class="required">*</span></label>
                    <input type="text" name="question" required placeholder="e.g. What is your minimum order quantity?">
                </div>
                <div class="form-group">
                    <label>Answer <span class="required">*</span></label>
                    <textarea name="answer" rows="3" required placeholder="Type the answer here..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:0.9rem;font-family:inherit;resize:vertical;"></textarea>
                </div>
            </div>
            <button type="submit" class="btn-primary">Add FAQ</button>
        </form>
    </div>

    <div class="dashboard-section">
        <h2>Manage FAQs</h2>
        <?php if (empty($faqs)): ?>
        <div class="activity-card"><p>No FAQs yet. Add your first FAQ above.</p></div>
        <?php else: ?>
        <div class="slides-list" id="faqsGrid">
            <?php foreach ($faqs as $faq): ?>
            <div class="slide-item faq-item" data-id="<?php echo $faq['id']; ?>">
                <div class="slide-handle" title="Drag to reorder">⠿</div>
                <div class="slide-details" style="flex:1;">
                    <form method="POST" class="faq-edit-form">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                        <input type="text" name="question" value="<?php echo htmlspecialchars($faq['question']); ?>" placeholder="Question" required style="width:100%;margin-bottom:6px;">
                        <textarea name="answer" rows="2" required style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:0.85rem;font-family:inherit;resize:vertical;"><?php echo htmlspecialchars($faq['answer']); ?></textarea>
                        <button type="submit" class="btn-sm btn-save" style="margin-top:6px;">Save</button>
                    </form>
                </div>
                <div class="slide-controls">
                    <span class="status-pill <?php echo $faq['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $faq['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                        <button type="submit" class="btn-sm <?php echo $faq['is_active'] ? 'btn-warn' : 'btn-activate'; ?>">
                            <?php echo $faq['is_active'] ? 'Disable' : 'Enable'; ?>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this FAQ?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <form method="POST" id="reorderForm" style="display:none">
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="order" id="reorderInput">
        </form>
        <?php endif; ?>
    </div>
</main>

<script>
const grid = document.getElementById('faqsGrid');
if (grid) {
    let dragEl = null;
    grid.querySelectorAll('.slide-item').forEach(item => {
        item.draggable = true;
        item.addEventListener('dragstart', () => { dragEl = item; item.classList.add('dragging'); });
        item.addEventListener('dragend', () => { item.classList.remove('dragging'); saveOrder(); });
        item.addEventListener('dragover', e => {
            e.preventDefault();
            const after = [...grid.querySelectorAll('.slide-item:not(.dragging)')].reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = e.clientY - box.top - box.height / 2;
                return offset < 0 && offset > closest.offset ? { offset, element: child } : closest;
            }, { offset: Number.NEGATIVE_INFINITY }).element;
            after ? grid.insertBefore(dragEl, after) : grid.appendChild(dragEl);
        });
    });
    function saveOrder() {
        const ids = [...grid.querySelectorAll('.slide-item')].map(c => c.dataset.id);
        document.getElementById('reorderInput').value = JSON.stringify(ids);
        document.getElementById('reorderForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
