<div class="flex justify-between items-center gap-4 my-6">
    <div>
        <?php if ($paginator->currentPage() > 1): ?>
            <a href="<?php echo htmlspecialchars($paginator->previousPageUrl()); ?>" class="text-blue-500 hover:text-blue-700 underline">
                ← Previous
            </a>
        <?php else: ?>
            <span class="text-gray-400 cursor-not-allowed">← Previous</span>
        <?php endif; ?>
    </div>

    <div class="text-sm text-gray-600">
        Page <strong><?php echo $paginator->currentPage(); ?></strong> of <strong><?php echo $paginator->lastPage(); ?></strong>
        (<?php echo $paginator->from(); ?>–<?php echo $paginator->to(); ?> of <?php echo $paginator->total(); ?>)
    </div>

    <div>
        <?php if ($paginator->hasMore()): ?>
            <a href="<?php echo htmlspecialchars($paginator->nextPageUrl()); ?>" class="text-blue-500 hover:text-blue-700 underline">
                Next →
            </a>
        <?php else: ?>
            <span class="text-gray-400 cursor-not-allowed">Next →</span>
        <?php endif; ?>
    </div>
</div>