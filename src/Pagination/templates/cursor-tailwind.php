<?php if ($paginator->hasMore()): ?>
    <div class="flex justify-center mt-6">
        <a href="<?php echo htmlspecialchars($paginator->nextPageUrl()); ?>" 
           class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">
            Next →
        </a>
    </div>
<?php endif; ?>