<?php
$pageTitle = 'Manage Departments'; $area = 'admin';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action'); $id = (int) post('department_id');
    $name = post('department_name'); $desc = mb_substr(post('description'), 0, 255);
    try {
        if ($action === 'add' || $action === 'edit') {
            if ($name === '' || mb_strlen($name) > 100) { flash('error', 'Department name is required.'); }
            elseif ($action === 'add') {
                $pdo->prepare('INSERT INTO departments (department_name, description) VALUES (?,?)')->execute([$name, $desc]);
                flash('success', 'Department added.');
            } else {
                $pdo->prepare('UPDATE departments SET department_name = ?, description = ? WHERE department_id = ?')->execute([$name, $desc, $id]);
                flash('success', 'Department updated.');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM departments WHERE department_id = ?')->execute([$id]);
            flash('success', 'Department deleted.');
        }
    } catch (PDOException $ex) {
        flash('error', $ex->getCode() === '23000'
            ? 'Not saved: the name already exists, or the department still has doctors. Move/delete its doctors first.'
            : 'Database error.');
    }
    redirect('/admin/departments.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId) { $st = $pdo->prepare('SELECT * FROM departments WHERE department_id = ?'); $st->execute([$editId]); $edit = $st->fetch(); }
$rows = $pdo->query('SELECT d.*, (SELECT COUNT(*) FROM doctors x WHERE x.department_id = d.department_id) AS doctors FROM departments d ORDER BY department_name')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>Manage Departments</h1>
<div class="card" style="max-width:700px">
  <h2><?= $edit ? 'Edit department' : 'Add department' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
    <input type="hidden" name="department_id" value="<?= (int) ($edit['department_id'] ?? 0) ?>">
    <label>Department name *</label><input name="department_name" required maxlength="100" value="<?= e($edit['department_name'] ?? '') ?>">
    <label>Description</label><input name="description" maxlength="255" value="<?= e($edit['description'] ?? '') ?>">
    <p><button class="btn" type="submit"><?= $edit ? 'Update' : 'Add department' ?></button>
       <?php if ($edit): ?><a class="btn btn-outline" href="departments.php">Cancel</a><?php endif; ?></p>
  </form>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>ID</th><th>Department</th><th>Description</th><th>Doctors</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= (int) $r['department_id'] ?></td><td><b><?= e($r['department_name']) ?></b></td><td><?= e($r['description']) ?></td><td><?= (int) $r['doctors'] ?></td>
      <td><a class="btn btn-sm btn-outline" href="?edit=<?= (int) $r['department_id'] ?>">Edit</a>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this department?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="department_id" value="<?= (int) $r['department_id'] ?>">
          <button class="btn btn-sm btn-danger">Delete</button></form></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
