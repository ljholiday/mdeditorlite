<?php
/**
 * Simple Markdown Editor
 * A lightweight web-based markdown file editor
 */

// Prevent any output before JSON responses
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

// Configuration
define('REPOS_PATH', __DIR__ . '/../repos');  // Path to your markdown repos (supports relative paths like '../repos')
define('PASSWORD', 'HaveMore4un!');  // Change this! Default: changeme123

// Resolve REPOS_PATH to absolute path (supports relative paths)
function getReposPath() {
    $path = REPOS_PATH;
    
    // If it's already an absolute path, return it
    if (substr($path, 0, 1) === '/' || preg_match('/^[a-zA-Z]:/', $path)) {
        return realpath($path) ?: $path;
    }
    
    // Handle relative paths
    $basePath = __DIR__;
    $fullPath = $basePath . '/' . $path;
    $resolved = realpath($fullPath);
    
    return $resolved ?: $fullPath;
}

$reposPath = getReposPath();

// Simple authentication
session_start();
if (isset($_POST['password'])) {
    if ($_POST['password'] === PASSWORD) {
        $_SESSION['authenticated'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check authentication - but handle AJAX requests differently
if (!isset($_SESSION['authenticated'])) {
    // If this is an AJAX request (has 'action' parameter), return JSON error
    if (isset($_GET['action']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated', 'redirect' => true]);
        exit;
    }
    
    // Otherwise show the login page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Markdown Editor - Login</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: #f5f5f5;
                padding: 1rem;
            }
            .login-box {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
            }
            h2 {
                margin-bottom: 1.5rem;
                color: #2c3e50;
            }
            form {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }
            input[type="password"] {
                padding: 0.75rem;
                font-size: 1rem;
                border: 1px solid #ddd;
                border-radius: 4px;
                width: 100%;
            }
            button {
                padding: 0.75rem 1rem;
                font-size: 1rem;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                width: 100%;
            }
            button:hover {
                background: #0056b3;
            }
            button:active {
                background: #004085;
            }
            @media (max-width: 480px) {
                .login-box {
                    padding: 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>Markdown Editor</h2>
            <form method="post" action="">
                <input type="password" name="password" placeholder="Enter password" autofocus required>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'list') {
        // List all markdown files
        $files = [];
        
        if (!is_dir($reposPath)) {
            echo json_encode(['success' => false, 'error' => 'REPOS_PATH directory not found: ' . $reposPath]);
            exit;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($reposPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['md', 'markdown'])) {
                $relativePath = str_replace($reposPath . '/', '', $file->getPathname());
                $files[] = [
                    'path' => $relativePath,
                    'name' => $file->getFilename(),
                    'dir' => dirname($relativePath)
                ];
            }
        }
        
        // Sort by directory, then filename
        usort($files, function($a, $b) {
            $dirCmp = strcmp($a['dir'], $b['dir']);
            return $dirCmp !== 0 ? $dirCmp : strcmp($a['name'], $b['name']);
        });
        
        echo json_encode(['success' => true, 'files' => $files]);
        exit;
    }
    
    if ($_GET['action'] === 'load' && isset($_GET['file'])) {
        // Load file content
        $filePath = $reposPath . '/' . $_GET['file'];
        
        // Security: prevent directory traversal
        $realPath = realpath($filePath);
        $realReposPath = realpath($reposPath);
        
        if ($realPath && $realReposPath && strpos($realPath, $realReposPath) === 0 && file_exists($filePath)) {
            $content = file_get_contents($filePath);
            echo json_encode(['success' => true, 'content' => $content]);
        } else {
            echo json_encode(['success' => false, 'error' => 'File not found or access denied']);
        }
        exit;
    }
    
    if ($_GET['action'] === 'save' && isset($_POST['file']) && isset($_POST['content'])) {
        // Save file content
        $filePath = $reposPath . '/' . $_POST['file'];
        
        // Security: prevent directory traversal
        $realPath = realpath(dirname($filePath));
        $realReposPath = realpath($reposPath);
        
        if ($realPath && $realReposPath && strpos($realPath, $realReposPath) === 0) {
            if (file_put_contents($filePath, $_POST['content']) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save file - check permissions']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Access denied - path verification failed']);
        }
        exit;
    }
    
    // Catch-all for unhandled actions
    if (isset($_GET['action'])) {
        echo json_encode(['success' => false, 'error' => 'Unknown action or missing parameters: ' . $_GET['action']]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown Editor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde@2.18.0/dist/easymde.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .hamburger-btn {
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 25px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
        }
        
        .hamburger-btn span {
            width: 100%;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s;
        }
        
        .hamburger-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }
        
        .hamburger-btn.active span:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }
        
        .header h1 {
            font-size: 1.25rem;
            font-weight: 500;
        }
        
        .header-right {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .current-file {
            font-size: 0.9rem;
            color: #ecf0f1;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .main {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .sidebar {
            width: 300px;
            background: #ecf0f1;
            border-right: 1px solid #bdc3c7;
            overflow-y: auto;
            padding: 1rem;
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }
        
        .sidebar.mobile-hidden {
            transform: translateX(-100%);
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        .sidebar h3 {
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .file-tree {
            list-style: none;
        }
        
        .tree-item {
            margin-bottom: 2px;
        }
        
        .tree-directory {
            font-weight: 600;
            color: #34495e;
            font-size: 0.85rem;
            cursor: pointer;
            user-select: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tree-directory:hover {
            background: #d5dbdb;
        }
        
        .tree-directory .expand-icon {
            font-size: 0.7rem;
            transition: transform 0.2s;
            display: inline-block;
            width: 12px;
            text-align: center;
        }
        
        .tree-directory.collapsed .expand-icon {
            transform: rotate(-90deg);
        }
        
        .directory-icon {
            font-size: 0.9rem;
            display: inline-block;
            width: 16px;
            height: 16px;
            position: relative;
        }
        
        .directory-icon::before {
            content: '';
            position: absolute;
            width: 14px;
            height: 11px;
            border: 2px solid #f39c12;
            border-radius: 2px;
            background: transparent;
            top: 2px;
        }
        
        .directory-icon::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 2px;
            background: #f39c12;
            top: 0;
            left: 0;
            border-radius: 2px 2px 0 0;
        }
        
        .directory-name {
            flex: 1;
        }
        
        .tree-children {
            overflow: hidden;
            transition: max-height 0.3s ease-out, opacity 0.2s ease-out;
            opacity: 1;
        }
        
        .tree-children.collapsed {
            max-height: 0 !important;
            opacity: 0;
        }
        
        .tree-file {
            padding: 0.4rem 0.5rem;
            cursor: pointer;
            border-radius: 4px;
            font-size: 0.85rem;
            transition: background 0.2s;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            word-break: break-word;
        }
        
        .tree-file:hover {
            background: #d5dbdb;
        }
        
        .tree-file.active {
            background: #3498db;
            color: white;
        }
        
        .file-icon {
            font-size: 0.85rem;
            display: inline-block;
            width: 16px;
            height: 16px;
            position: relative;
        }
        
        .file-icon::before {
            content: '';
            position: absolute;
            width: 12px;
            height: 15px;
            border: 2px solid #95a5a6;
            border-radius: 1px;
            background: white;
            top: 0;
            left: 0;
        }
        
        .file-icon::after {
            content: '';
            position: absolute;
            width: 6px;
            height: 1px;
            background: #95a5a6;
            top: 5px;
            left: 3px;
            box-shadow: 0 3px 0 #95a5a6, 0 6px 0 #95a5a6;
        }
        
        .tree-file.active .file-icon::before {
            border-color: white;
        }
        
        .tree-file.active .file-icon::after {
            background: white;
            box-shadow: 0 3px 0 white, 0 6px 0 white;
        }
        
        .file-name {
            flex: 1;
        }
        
        .editor-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .editor-wrapper {
            flex: 1;
            overflow: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
        }
        
        .CodeMirror {
            height: 100% !important;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .CodeMirror-scroll {
            overflow: auto !important;
        }
        
        .no-file-selected {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: #7f8c8d;
            font-size: 1.1rem;
            text-align: center;
            padding: 1rem;
        }
        
        .status-message {
            position: fixed;
            top: 70px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
        }
        
        .status-message.success {
            background: #27ae60;
            color: white;
        }
        
        .status-message.error {
            background: #e74c3c;
            color: white;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 60px; /* Account for fixed header */
            }
            
            .header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1100;
            }
            
            .hamburger-btn {
                display: flex;
            }
            
            .sidebar {
                position: fixed;
                top: 60px; /* Below fixed header */
                left: 0;
                bottom: 0;
                z-index: 1000;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                transform: translateX(0);
                transition: transform 0.3s ease;
            }
            
            .sidebar.mobile-hidden {
                transform: translateX(-100%);
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 60px; /* Below fixed header */
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                display: none;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
            
            .current-file {
                display: none;
            }
            
            .header h1 {
                font-size: 1rem;
            }
            
            .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }
            
            .header-right {
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .sidebar {
                width: 250px;
            }
            
            .header {
                padding: 0.5rem;
            }
            
            .header-right {
                gap: 0.4rem;
            }
            
            .header-left {
                gap: 0.5rem;
            }
            
            .btn {
                padding: 0.35rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .header h1 {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <h1>Markdown Editor</h1>
        </div>
        <div class="header-right">
            <button class="btn btn-success btn-mobile" id="saveBtn" disabled>Save</button>
            <button class="btn btn-primary btn-mobile" id="refreshBtn">Refresh</button>
            <span class="current-file" id="currentFile">No file selected</span>
            <button class="btn btn-secondary" onclick="location.href='?logout=1'">Logout</button>
        </div>
    </div>
    
    <div class="main">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <div class="sidebar" id="sidebar">
            <h3>Files</h3>
            <div id="fileList">Loading...</div>
        </div>
        
        <div class="editor-container">
            <div class="editor-wrapper">
                <div id="noFileSelected" class="no-file-selected">
                    Select a file from the sidebar to begin editing
                </div>
                <textarea id="editor" style="display: none;"></textarea>
            </div>
        </div>
    </div>
    
    <div class="status-message" id="statusMessage"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/easymde@2.18.0/dist/easymde.min.js"></script>
    <script>
        let editor = null;
        let currentFile = null;
        let originalContent = '';
        let expandedDirs = new Set(); // Track which directories are expanded
        
        // Initialize EasyMDE
        function initEditor() {
            editor = new EasyMDE({
                element: document.getElementById('editor'),
                autosave: {
                    enabled: false
                },
                spellChecker: false,
                status: ['lines', 'words', 'cursor'],
                toolbar: [
                    'bold', 'italic', 'heading', '|',
                    'quote', 'unordered-list', 'ordered-list', '|',
                    'link', 'image', '|',
                    'preview', 'side-by-side', 'fullscreen', '|',
                    'guide'
                ],
                minHeight: '400px'
            });
            
            editor.codemirror.on('change', function() {
                checkForChanges();
            });
        }
        
        // Load file list
        function loadFileList() {
            fetch('?action=list')
                .then(response => {
                    if (response.status === 401) {
                        window.location.reload();
                        return;
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) return; // Handle reload case
                    
                    if (data.redirect) {
                        window.location.reload();
                        return;
                    }
                    
                    if (data.success) {
                        displayFileList(data.files);
                    } else {
                        document.getElementById('fileList').innerHTML = '<p style="color: red;">Error: ' + (data.error || 'Unknown error') + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading files:', error);
                    document.getElementById('fileList').innerHTML = '<p style="color: red;">Error loading files</p>';
                });
        }
        
        // Build hierarchical tree structure from flat file list
        function buildFileTree(files) {
            const tree = { name: 'root', type: 'directory', children: [], files: [] };
            
            files.forEach(file => {
                const parts = file.path.split('/');
                const fileName = parts.pop();
                
                let currentNode = tree;
                
                // Navigate/create directory structure
                parts.forEach(part => {
                    if (part === '.') return; // Skip root marker
                    
                    let childNode = currentNode.children.find(c => c.name === part && c.type === 'directory');
                    if (!childNode) {
                        childNode = { name: part, type: 'directory', children: [], files: [], path: '' };
                        currentNode.children.push(childNode);
                    }
                    currentNode = childNode;
                });
                
                // Add file to the current directory node
                currentNode.files.push({
                    name: fileName,
                    path: file.path,
                    fullData: file
                });
            });
            
            // Sort directories and files alphabetically
            function sortNode(node) {
                node.children.sort((a, b) => a.name.localeCompare(b.name));
                node.files.sort((a, b) => a.name.localeCompare(b.name));
                node.children.forEach(sortNode);
            }
            sortNode(tree);
            
            return tree;
        }
        
        // Render tree node recursively
        function renderTreeNode(node, level = 0, parentPath = '') {
            let html = '';
            const indent = level * 20; // 20px per level
            
            // Render directories
            node.children.forEach(child => {
                const fullPath = parentPath ? `${parentPath}/${child.name}` : child.name;
                const dirId = 'dir-' + fullPath.replace(/[^a-zA-Z0-9]/g, '-');
                const isCollapsed = !expandedDirs.has(dirId); // Default collapsed (not in set = collapsed)
                
                html += '<div class="tree-item" style="padding-left: ' + indent + 'px;">';
                html += `<div class="tree-directory ${isCollapsed ? 'collapsed' : ''}" data-dir-id="${dirId}">`;
                html += `<span class="expand-icon">▼</span>`;
                html += `<span class="directory-icon"></span>`;
                html += `<span class="directory-name">${escapeHtml(child.name)}</span>`;
                html += `</div>`;
                html += `<div class="tree-children ${isCollapsed ? 'collapsed' : ''}" id="${dirId}">`;
                
                // Recursively render children
                html += renderTreeNode(child, level + 1, fullPath);
                
                html += '</div>';
                html += '</div>';
            });
            
            // Render files at this level
            node.files.forEach(file => {
                const active = currentFile === file.path ? 'active' : '';
                html += `<div class="tree-file ${active}" style="padding-left: ${indent + 20}px;" data-file-path="${file.path.replace(/"/g, '&quot;')}">`;
                html += `<span class="file-icon"></span>`;
                html += `<span class="file-name">${escapeHtml(file.name)}</span>`;
                html += `</div>`;
            });
            
            return html;
        }
        
        // Display file list as hierarchical tree
        function displayFileList(files) {
            const fileList = document.getElementById('fileList');
            
            if (files.length === 0) {
                fileList.innerHTML = '<p style="color: #7f8c8d;">No markdown files found</p>';
                return;
            }
            
            // Build tree structure
            const tree = buildFileTree(files);
            
            // Render tree
            let html = '<div class="file-tree">';
            html += renderTreeNode(tree, 0);
            html += '</div>';
            
            fileList.innerHTML = html;
            
            // Set initial heights for smooth transitions
            requestAnimationFrame(() => {
                document.querySelectorAll('.tree-children').forEach(el => {
                    if (!el.classList.contains('collapsed')) {
                        el.style.maxHeight = el.scrollHeight + 'px';
                    } else {
                        el.style.maxHeight = '0';
                    }
                });
            });
            
            // Add event delegation for clicks
            setupTreeEventListeners();
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Setup event listeners for tree interactions
        let treeListenerAdded = false;
        function setupTreeEventListeners() {
            const fileList = document.getElementById('fileList');
            
            // Only add listener once
            if (treeListenerAdded) return;
            treeListenerAdded = true;
            
            // Use event delegation for better performance
            fileList.addEventListener('click', function(e) {
                // Handle directory clicks
                const directoryEl = e.target.closest('.tree-directory');
                if (directoryEl) {
                    const dirId = directoryEl.dataset.dirId;
                    toggleDirectory(dirId);
                    return;
                }
                
                // Handle file clicks
                const fileEl = e.target.closest('.tree-file');
                if (fileEl) {
                    const filePath = fileEl.dataset.filePath;
                    loadFile(filePath);
                    return;
                }
            });
        }
        
        // Toggle directory collapse/expand
        function toggleDirectory(dirId) {
            const childrenDiv = document.getElementById(dirId);
            const directoryDiv = childrenDiv.previousElementSibling;
            
            if (childrenDiv.classList.contains('collapsed')) {
                // Expand
                childrenDiv.classList.remove('collapsed');
                directoryDiv.classList.remove('collapsed');
                childrenDiv.style.maxHeight = childrenDiv.scrollHeight + 'px';
                expandedDirs.add(dirId); // Track as expanded
            } else {
                // Collapse
                childrenDiv.classList.add('collapsed');
                directoryDiv.classList.add('collapsed');
                childrenDiv.style.maxHeight = '0';
                expandedDirs.delete(dirId); // Remove from expanded
            }
        }
        
        // Load file content
        function loadFile(filePath) {
            if (editor && hasUnsavedChanges()) {
                if (!confirm('You have unsaved changes. Do you want to discard them?')) {
                    return;
                }
            }
            
            fetch(`?action=load&file=${encodeURIComponent(filePath)}`)
                .then(response => {
                    if (response.status === 401) {
                        window.location.reload();
                        return;
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) return; // Handle reload case
                    
                    if (data.redirect) {
                        window.location.reload();
                        return;
                    }
                    
                    if (data.success) {
                        currentFile = filePath;
                        originalContent = data.content;
                        
                        if (!editor) {
                            document.getElementById('noFileSelected').style.display = 'none';
                            document.getElementById('editor').style.display = 'block';
                            initEditor();
                        }
                        
                        editor.value(data.content);
                        document.getElementById('currentFile').textContent = filePath;
                        document.getElementById('saveBtn').disabled = true;
                        
                        // Update active state in file list
                        document.querySelectorAll('.tree-file').forEach(item => {
                            if (item.dataset.filePath === filePath) {
                                item.classList.add('active');
                            } else {
                                item.classList.remove('active');
                            }
                        });
                    } else {
                        showStatus('Error loading file: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading file:', error);
                    showStatus('Error loading file', 'error');
                });
        }
        
        // Save file
        function saveFile() {
            if (!currentFile) {
                showStatus('Error: No file selected', 'error');
                return;
            }
            
            const content = editor.value();
            const formData = new FormData();
            formData.append('file', currentFile);
            formData.append('content', content);
            
            fetch('?action=save', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    // Handle authentication errors
                    if (response.status === 401) {
                        showStatus('Session expired. Reloading...', 'error');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                        return response.json();
                    }
                    
                    return response.json();
                })
                .then(data => {
                    // Check for redirect flag (authentication required)
                    if (data && data.redirect) {
                        showStatus('Session expired. Please log in again.', 'error');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                        return;
                    }
                    
                    if (data && data.success) {
                        originalContent = content;
                        document.getElementById('saveBtn').disabled = true;
                        showStatus('File saved successfully!', 'success');
                    } else {
                        showStatus('Error saving file: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Save error:', error);
                    showStatus('Error saving file: ' + error.message, 'error');
                });
        }
        
        // Check for unsaved changes
        function hasUnsavedChanges() {
            if (!editor || !currentFile) return false;
            return editor.value() !== originalContent;
        }
        
        function checkForChanges() {
            if (currentFile) {
                document.getElementById('saveBtn').disabled = !hasUnsavedChanges();
            }
        }
        
        // Show status message
        function showStatus(message, type) {
            const statusEl = document.getElementById('statusMessage');
            statusEl.textContent = message;
            statusEl.className = 'status-message ' + type;
            statusEl.style.display = 'block';
            
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 3000);
        }
        
        // Event listeners
        document.getElementById('saveBtn').addEventListener('click', saveFile);
        document.getElementById('refreshBtn').addEventListener('click', loadFileList);
        
        // Hamburger menu functionality
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            hamburgerBtn.classList.toggle('active');
            sidebar.classList.toggle('mobile-hidden');
            sidebarOverlay.classList.toggle('active');
        }
        
        hamburgerBtn.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);
        
        // Close sidebar when selecting a file on mobile
        if (window.innerWidth <= 768) {
            // This will be handled through event delegation
            document.getElementById('fileList').addEventListener('click', function(e) {
                if (e.target.closest('.tree-file')) {
                    // Close sidebar on mobile after selecting file
                    if (window.innerWidth <= 768) {
                        toggleSidebar();
                    }
                }
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S or Cmd+S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (!document.getElementById('saveBtn').disabled) {
                    saveFile();
                }
            }
        });
        
        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        // Load file list on page load
        loadFileList();
        
        // Initialize sidebar state for mobile
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.add('mobile-hidden');
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                // Desktop view - ensure sidebar is visible
                document.getElementById('sidebar').classList.remove('mobile-hidden');
                document.getElementById('sidebarOverlay').classList.remove('active');
                document.getElementById('hamburgerBtn').classList.remove('active');
            } else if (!document.getElementById('sidebar').classList.contains('mobile-hidden')) {
                // Switching to mobile - hide sidebar
                document.getElementById('sidebar').classList.add('mobile-hidden');
            }
        });
    </script>
</body>
</html>
