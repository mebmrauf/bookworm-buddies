<?php
// A moderation button. With $needsReason it opens a small form, because the person affected is told why.
function admin_action($do, $id, $label, $needsReason = false, $confirm = null) {
    $back = e($_SERVER['REQUEST_URI'] ?? '/admin/index.php');
    $hidden = csrf_field() . '<input type="hidden" name="do" value="' . e($do) . '"><input type="hidden" name="id" value="' . (int) $id . '"><input type="hidden" name="back" value="' . $back . '">';
    if ($needsReason) {
        return '<details><summary>' . e($label) . '</summary><form class="form" action="/admin/action.php" method="post">' . $hidden
            . '<div class="field"><label for="reason-' . e($do . $id) . '">Reason (the person is told this)</label>'
            . '<input id="reason-' . e($do . $id) . '" type="text" name="reason" maxlength="255" required></div>'
            . '<div><button type="submit" class="btn btn--danger">' . e($label) . '</button></div></form></details>';
    }
    return '<form action="/admin/action.php" method="post"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . $hidden
        . '<button type="submit" class="btn btn--ghost">' . e($label) . '</button></form>';
}
