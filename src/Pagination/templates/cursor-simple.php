<div class="flex justify-center gap-4 my-6">
    <?php if ($paginator->hasMore()): ?>
        <a href="<?php echo htmlspecialchars($paginator->nextPageUrl()); ?>" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Load More
        </a>
    <?php endif; ?>
</div>