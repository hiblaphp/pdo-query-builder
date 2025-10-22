<?php

use Hibla\PdoQueryBuilder\DB;

require 'vendor/autoload.php';

try {
    // Fetch cursor paginated results
    $results = await(
        DB::table('users')
            ->toObject()
            ->cursorPaginate(perPage: 5)
    );

} catch (\Exception $e) {
    $error = "Error: " . htmlspecialchars($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursor Pagination Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .template-section {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .template-section h3 {
            margin-bottom: 20px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">📄 Cursor Pagination Render Test</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <!-- Users Table -->
            <div class="mb-4">
                <h2>Users List (Cursor Pagination)</h2>
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($results->items()) > 0): ?>
                            <?php foreach ($results->items() as $user): ?>
                                <tr>
                                    <td><?php echo $user->id; ?></td>
                                    <td><?php echo htmlspecialchars($user->name); ?></td>
                                    <td><?php echo htmlspecialchars($user->email); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Stats -->
            <div class="alert alert-info">
                <strong>Results:</strong>
                Showing <?php echo count($results->items()); ?> users
                <?php if ($results->hasMore()): ?>
                    (More results available)
                <?php else: ?>
                    (End of results)
                <?php endif; ?>
            </div>

            <!-- Simple Template -->
            <div class="template-section">
                <h3>📋 Simple Template</h3>
                <?php echo $results->render('cursor-simple'); ?>
            </div>

            <!-- Bootstrap Template -->
            <div class="template-section">
                <h3>🅱️ Bootstrap Template</h3>
                <?php echo $results->render('cursor-bootstrap'); ?>
            </div>

            <!-- Tailwind Template -->
            <div class="template-section">
                <h3>🎨 Tailwind Template</h3>
                <?php echo $results->render('cursor-tailwind'); ?>
            </div>

            <!-- Debug Info -->
            <div class="alert alert-secondary">
                <h5>Debug Information</h5>
                <pre><code><?php echo htmlspecialchars(json_encode([
                    'perPage' => $results->perPage(),
                    'itemsCount' => count($results->items()),
                    'hasMore' => $results->hasMore(),
                    'nextCursor' => $results->nextCursor(),
                    'cursorColumn' => $results->getCursorColumn(),
                    'path' => $results->path(),
                ], JSON_PRETTY_PRINT)); ?></code></pre>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>