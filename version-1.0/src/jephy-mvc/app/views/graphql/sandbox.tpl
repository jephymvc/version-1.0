{extends file="graphql-layout.tpl"}

{block name="title"}{$sandbox_title}{/block}

{block name="styles"}
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        background: #1e1e1e;
        color: #cccccc;
        height: 100vh;
        overflow: hidden;
    }

    /* Layout */
    .sandbox-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
    }

    /* Header */
    .sandbox-header {
        background: #252526;
        padding: 12px 20px;
        border-bottom: 1px solid #3e3e42;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .header-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .header-title h1 {
        font-size: 1.3rem;
        color: #4ec9b0;
        font-weight: 500;
    }

    .header-title .badge {
        background: #0e639c;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-family: monospace;
    }

    .sandbox-controls {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    button {
        background: #0e639c;
        color: white;
        border: none;
        padding: 6px 14px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    button:hover {
        background: #1177bb;
        transform: translateY(-1px);
    }

    button.secondary {
        background: #3e3e42;
    }

    button.secondary:hover {
        background: #4e4e54;
    }

    button.danger {
        background: #a1260d;
    }

    button.danger:hover {
        background: #c42b1c;
    }

    button.success {
        background: #2d7a4b;
    }

    button.success:hover {
        background: #379e60;
    }

    /* Main Content */
    .main-content {
        display: flex;
        flex: 1;
        overflow: hidden;
        gap: 1px;
        background: #3e3e42;
    }

    /* Panels */
    .panel {
        background: #1e1e1e;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .query-panel {
        flex: 2;
    }

    .variables-panel {
        flex: 1;
    }

    .response-panel {
        flex: 2;
    }

    .panel-header {
        background: #2d2d30;
        padding: 8px 12px;
        border-bottom: 1px solid #3e3e42;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .panel-header span:first-child {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .panel-actions {
        display: flex;
        gap: 8px;
    }

    .panel-actions button {
        background: none;
        padding: 2px 6px;
        font-size: 11px;
    }

    .panel-actions button:hover {
        background: #0e639c;
        transform: none;
    }

    /* Editors */
    .editor-container {
        flex: 1;
        position: relative;
        overflow: hidden;
    }

    textarea, .code-editor {
        width: 100%;
        height: 100%;
        background: #1e1e1e;
        color: #d4d4d4;
        border: none;
        padding: 15px;
        font-family: 'Monaco', 'Menlo', 'Consolas', 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.5;
        resize: none;
        outline: none;
        tab-size: 4;
    }

    .response-content {
        flex: 1;
        overflow: auto;
        padding: 15px;
        font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
        font-size: 13px;
        line-height: 1.5;
    }

    .response-content pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
        color: #d4d4d4;
    }

    /* Status Bar */
    .status-bar {
        background: #007acc;
        padding: 4px 15px;
        font-size: 11px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
    }

    .status-left {
        display: flex;
        gap: 20px;
    }

    .status-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .status-error {
        background: #f48771;
        color: #1e1e1e;
        padding: 2px 8px;
        border-radius: 3px;
    }

    /* Modals */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: #2d2d30;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        animation: modalSlideIn 0.2s ease;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #3e3e42;
        font-weight: 600;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-body input, .modal-body select {
        width: 100%;
        padding: 8px 12px;
        background: #3e3e42;
        border: 1px solid #555;
        color: white;
        border-radius: 4px;
        font-size: 14px;
        margin-top: 5px;
    }

    .modal-body input:focus {
        outline: none;
        border-color: #0e639c;
    }

    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #3e3e42;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    /* History Sidebar */
    .history-sidebar {
        position: fixed;
        right: -300px;
        top: 0;
        width: 300px;
        height: 100vh;
        background: #252526;
        border-left: 1px solid #3e3e42;
        z-index: 999;
        transition: right 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .history-sidebar.active {
        right: 0;
    }

    .history-header {
        padding: 15px;
        border-bottom: 1px solid #3e3e42;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .history-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
    }

    .history-item {
        padding: 10px;
        margin-bottom: 8px;
        background: #1e1e1e;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .history-item:hover {
        background: #0e639c;
    }

    .history-item-name {
        font-weight: 600;
        margin-bottom: 5px;
    }

    .history-item-preview {
        font-size: 11px;
        color: #888;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Keyboard shortcuts hint */
    .shortcuts-hint {
        position: fixed;
        bottom: 50px;
        right: 20px;
        background: #252526;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 10px;
        border: 1px solid #3e3e42;
        opacity: 0.6;
        transition: opacity 0.2s;
        z-index: 100;
    }

    .shortcuts-hint:hover {
        opacity: 1;
    }

    /* JSON Syntax Highlighting */
    .json-key {
        color: #9cdcfe;
    }
    .json-string {
        color: #ce9178;
    }
    .json-number {
        color: #b5cea8;
    }
    .json-boolean {
        color: #569cd6;
    }
    .json-null {
        color: #569cd6;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            flex-direction: column;
        }
        
        .sandbox-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .sandbox-controls {
            justify-content: center;
        }
        
        .shortcuts-hint {
            display: none;
        }
    }
</style>
{/block}

{block name="content"}
<div class="sandbox-container">
    <!-- Header -->
    <div class="sandbox-header">
        <div class="header-title">
            <h1>⚡ {$sandbox_title}</h1>
            <span class="badge">GraphQL</span>
        </div>
        <div class="sandbox-controls">
            <button onclick="executeQuery()" class="success">
                <span>▶</span> Execute (Ctrl+Enter)
            </button>
            <button onclick="showSaveModal()" class="secondary">
                <span>💾</span> Save
            </button>
            <button onclick="loadHistory()" class="secondary">
                <span>📋</span> History
            </button>
            <button onclick="clearResponse()" class="danger">
                <span>🗑</span> Clear
            </button>
            <button onclick="formatQuery()" class="secondary">
                <span>✨</span> Format
            </button>
            <button onclick="showSchema()" class="secondary">
                <span>📚</span> Schema
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Query Panel -->
        <div class="panel query-panel">
            <div class="panel-header">
                <span>📝 QUERY</span>
                <div class="panel-actions">
                    <button onclick="insertTemplate('query')">Template</button>
                </div>
            </div>
            <div class="editor-container">
                <textarea id="query-editor" placeholder="# Write your GraphQL query here...&#10;# Example:&#10;# query {&#10;#   user(id: 1) {&#10;#     name&#10;#     email&#10;#   }&#10;# }">{$default_query|escape}</textarea>
            </div>
        </div>

        <!-- Variables Panel -->
        <div class="panel variables-panel">
            <div class="panel-header">
                <span>🔧 VARIABLES (JSON)</span>
                <div class="panel-actions">
                    <button onclick="formatVariables()">Format</button>
                </div>
            </div>
            <div class="editor-container">
                <textarea id="variables-editor" placeholder='{"key": "value"}'>{}</textarea>
            </div>
        </div>

        <!-- Response Panel -->
        <div class="panel response-panel">
            <div class="panel-header">
                <span>📊 RESPONSE</span>
                <div class="panel-actions">
                    <button onclick="copyResponse()">Copy</button>
                    <button onclick="downloadResponse()">Download</button>
                </div>
            </div>
            <div id="response-content" class="response-content">
                <pre style="color:#888;"># Click Execute or press Ctrl+Enter to run your query</pre>
            </div>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="status-bar">
        <div class="status-left">
            <span class="status-item">🟢 Status: <span id="status-text">Ready</span></span>
            <span class="status-item">⏱ Duration: <span id="duration-text">0ms</span></span>
        </div>
        <div>
            <span class="status-item">📊 Query length: <span id="query-length">0</span> chars</span>
        </div>
    </div>
</div>

<!-- Save Query Modal -->
<div id="save-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <span>💾 Save Query</span>
        </div>
        <div class="modal-body">
            <label>Query Name:</label>
            <input type="text" id="query-name" placeholder="e.g., Get User by ID" />
        </div>
        <div class="modal-footer">
            <button onclick="closeSaveModal()" class="secondary">Cancel</button>
            <button onclick="confirmSave()" class="success">Save</button>
        </div>
    </div>
</div>

<!-- History Sidebar -->
<div id="history-sidebar" class="history-sidebar">
    <div class="history-header">
        <span>📋 Saved Queries</span>
        <button onclick="closeHistory()" class="secondary" style="padding: 4px 8px;">✕</button>
    </div>
    <div id="history-list" class="history-list">
        <div style="text-align: center; color: #888; padding: 20px;">No saved queries yet</div>
    </div>
</div>

<!-- Keyboard Shortcuts Hint -->
<div class="shortcuts-hint">
    <strong>⌨️ Shortcuts:</strong> Ctrl+Enter: Execute | Ctrl+S: Save | Ctrl+Shift+F: Format
</div>

<script>
    // DOM Elements
    const queryEditor = document.getElementById('query-editor');
    const variablesEditor = document.getElementById('variables-editor');
    const responseContent = document.getElementById('response-content');
    const statusText = document.getElementById('status-text');
    const durationText = document.getElementById('duration-text');
    const queryLengthSpan = document.getElementById('query-length');
    
    let csrfToken = '{$csrf_token}';
    let endpointUrl = '{$endpoint_url}';
    let savedQueries = {$saved_queries|json_encode};

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        updateQueryLength();
        queryEditor.addEventListener('input', updateQueryLength);
        loadFromHash();
    });

    // Update query length display
    function updateQueryLength() {
        queryLengthSpan.textContent = queryEditor.value.length;
    }

    // Execute Query
    async function executeQuery() {
        const query = queryEditor.value.trim();
        if (!query) {
            showError('Please enter a query');
            return;
        }

        let variables = {};
        try {
            const varsText = variablesEditor.value.trim();
            variables = varsText ? JSON.parse(varsText) : {};
        } catch (e) {
            showError('Invalid JSON in variables: ' + e.message);
            return;
        }

        updateStatus('Executing...', '');
        const startTime = performance.now();

        try {
            const response = await fetch(endpointUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    query: query,
                    variables: variables
                })
            });

            const duration = performance.now() - startTime;
            const result = await response.json();

            displayResponse(result);
            
            if (response.ok && !result.errors) {
                updateStatus('Success', `${duration.toFixed(2)}ms`);
            } else if (result.errors) {
                updateStatus('Error', `${duration.toFixed(2)}ms`, true);
                showError(result.errors[0]?.message || 'GraphQL Error');
            } else {
                updateStatus('Completed', `${duration.toFixed(2)}ms`);
            }

        } catch (error) {
            const duration = performance.now() - startTime;
            displayResponse({error: error.message, stack: error.stack});
            updateStatus('Network Error', `${duration.toFixed(2)}ms`, true);
            showError(error.message);
        }
    }

    // Display response with syntax highlighting
    function displayResponse(data) {
        const jsonStr = JSON.stringify(data, null, 2);
        const highlighted = syntaxHighlightJSON(jsonStr);
        responseContent.innerHTML = `<pre>${highlighted}</pre>`;
    }

    // JSON syntax highlighting
    function syntaxHighlightJSON(json) {
        json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
            let cls = 'json-number';
            if (/^"/.test(match)) {
                if (/:$/.test(match)) {
                    cls = 'json-key';
                } else {
                    cls = 'json-string';
                }
            } else if (/true|false/.test(match)) {
                cls = 'json-boolean';
            } else if (/null/.test(match)) {
                cls = 'json-null';
            }
            return '<span class="' + cls + '">' + match + '</span>';
        });
    }

    // Save Query
    function showSaveModal() {
        document.getElementById('save-modal').classList.add('active');
        document.getElementById('query-name').focus();
    }

    function closeSaveModal() {
        document.getElementById('save-modal').classList.remove('active');
    }

    function confirmSave() {
        const name = document.getElementById('query-name').value.trim();
        if (!name) {
            alert('Please enter a query name');
            return;
        }

        const query = queryEditor.value.trim();
        if (!query) {
            alert('No query to save');
            return;
        }

        // Save to localStorage
        const saved = getSavedQueriesFromStorage();
        saved.push({
            id: Date.now(),
            name: name,
            query: query,
            variables: variablesEditor.value,
            createdAt: new Date().toISOString()
        });
        localStorage.setItem('graphql_saved_queries', JSON.stringify(saved));
        
        closeSaveModal();
        alert('Query saved successfully!');
        
        if (document.getElementById('history-sidebar').classList.contains('active')) {
            loadHistory();
        }
    }

    function getSavedQueriesFromStorage() {
        const saved = localStorage.getItem('graphql_saved_queries');
        return saved ? JSON.parse(saved) : [];
    }

    // Load History
    function loadHistory() {
        const sidebar = document.getElementById('history-sidebar');
        const historyList = document.getElementById('history-list');
        
        const saved = getSavedQueriesFromStorage();
        
        if (saved.length === 0) {
            historyList.innerHTML = '<div style="text-align: center; color: #888; padding: 20px;">No saved queries yet</div>';
        } else {
            historyList.innerHTML = saved.reverse().map(item => `
                <div class="history-item" onclick="loadQueryFromHistory(${item.id})">
                    <div class="history-item-name">${escapeHtml(item.name)}</div>
                    <div class="history-item-preview">${escapeHtml(item.query.substring(0, 50))}...</div>
                    <div style="font-size: 10px; color: #888; margin-top: 5px;">${new Date(item.createdAt).toLocaleString()}</div>
                </div>
            `).join('');
        }
        
        sidebar.classList.add('active');
    }

    function closeHistory() {
        document.getElementById('history-sidebar').classList.remove('active');
    }

    function loadQueryFromHistory(id) {
        const saved = getSavedQueriesFromStorage();
        const item = saved.find(q => q.id === id);
        if (item) {
            queryEditor.value = item.query;
            variablesEditor.value = item.variables || '{}';
            updateQueryLength();
            closeHistory();
            executeQuery();
        }
    }

    // Format Query
    function formatQuery() {
        try {
            // Simple formatting: add newlines after braces
            let query = queryEditor.value;
            query = query.replace(/\s+/g, ' ').trim();
            query = query.replace(/{/g, '{\n  ');
            query = query.replace(/}/g, '\n}');
            query = query.replace(/,/g, ',\n  ');
            query = query.replace(/\n\s*\n/g, '\n');
            queryEditor.value = query;
        } catch (e) {
            console.error('Format error:', e);
        }
    }

    function formatVariables() {
        try {
            const vars = JSON.parse(variablesEditor.value);
            variablesEditor.value = JSON.stringify(vars, null, 2);
        } catch (e) {
            // Not valid JSON, ignore
        }
    }

    // Response utilities
    function clearResponse() {
        responseContent.innerHTML = '<pre style="color:#888;"># Response cleared</pre>';
        updateStatus('Cleared', '');
    }

    function copyResponse() {
        const text = responseContent.innerText;
        navigator.clipboard.writeText(text).then(() => {
            showTemporaryStatus('Copied to clipboard!');
        });
    }

    function downloadResponse() {
        const text = responseContent.innerText;
        const blob = new Blob([text], {type: 'application/json'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `graphql-response-${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    // Schema Explorer
    function showSchema() {
        fetch(endpointUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                query: `
                    query IntrospectionQuery {
                        __schema {
                            types {
                                name
                                kind
                                description
                                fields {
                                    name
                                    type {
                                        name
                                        kind
                                        ofType {
                                            name
                                            kind
                                        }
                                    }
                                }
                            }
                            queryType {
                                name
                                fields {
                                    name
                                    description
                                    args {
                                        name
                                        type {
                                            name
                                        }
                                    }
                                }
                            }
                            mutationType {
                                name
                                fields {
                                    name
                                    description
                                }
                            }
                        }
                    }
                `
            })
        })
        .then(res => res.json())
        .then(data => {
            displayResponse(data);
            showTemporaryStatus('Schema loaded');
        })
        .catch(err => {
            showError('Failed to load schema: ' + err.message);
        });
    }

    // Insert Template
    function insertTemplate(type) {
        const templates = {
            query: `query {
  # Simple query
  hello
  
  # Query with arguments
  user(id: 1) {
    id
    name
    email
  }
}`,
            mutation: `mutation {
  createUser(name: "John Doe", email: "john@example.com") {
    id
    name
    email
  }
}`,
            fragment: `fragment UserFields on User {
  id
  name
  email
  createdAt
}

query GetUser {
  user(id: 1) {
    ...UserFields
  }
}`
        };
        
        queryEditor.value = templates[type] || templates.query;
        updateQueryLength();
    }

    // Utility functions
    function updateStatus(status, duration, isError = false) {
        statusText.innerHTML = status;
        durationText.textContent = duration;
        if (isError) {
            statusText.style.color = '#f48771';
        } else {
            statusText.style.color = '';
        }
    }

    function showError(message) {
        statusText.innerHTML = `❌ ${escapeHtml(message)}`;
        statusText.style.color = '#f48771';
        setTimeout(() => {
            if (statusText.innerHTML.includes(message)) {
                statusText.innerHTML = 'Ready';
                statusText.style.color = '';
            }
        }, 5000);
    }

    function showTemporaryStatus(message) {
        const originalStatus = statusText.innerHTML;
        statusText.innerHTML = message;
        setTimeout(() => {
            if (statusText.innerHTML === message) {
                statusText.innerHTML = originalStatus;
            }
        }, 2000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Save to URL hash
    function saveToHash() {
        const state = {
            q: queryEditor.value,
            v: variablesEditor.value
        };
        window.location.hash = btoa(JSON.stringify(state));
    }

    function loadFromHash() {
        if (window.location.hash) {
            try {
                const state = JSON.parse(atob(window.location.hash.slice(1)));
                if (state.q) queryEditor.value = state.q;
                if (state.v) variablesEditor.value = state.v;
                updateQueryLength();
            } catch (e) {}
        }
    }

    // Auto-save to hash
    let hashTimeout;
    queryEditor.addEventListener('input', () => {
        clearTimeout(hashTimeout);
        hashTimeout = setTimeout(saveToHash, 1000);
        updateQueryLength();
    });
    variablesEditor.addEventListener('input', () => {
        clearTimeout(hashTimeout);
        hashTimeout = setTimeout(saveToHash, 1000);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        // Ctrl+Enter or Cmd+Enter
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            executeQuery();
        }
        // Ctrl+S
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            showSaveModal();
        }
        // Ctrl+Shift+F
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'F') {
            e.preventDefault();
            formatQuery();
        }
        // Escape to close modals
        if (e.key === 'Escape') {
            closeSaveModal();
            closeHistory();
        }
    });

    // Close modal when clicking overlay
    document.getElementById('save-modal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('save-modal')) {
            closeSaveModal();
        }
    });
</script>
{/block}