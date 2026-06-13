/* ==========================================================================
   SANDBOX HRIS APPLICATION ENGINE - STATE, INTERACTION & CANVAS DRAWING
   ========================================================================== */

// --------------------------------------------------------------------------
// 1. APPLICATION STATE
// --------------------------------------------------------------------------
const DEMO_NODES = [
    // Column 1: Company
    { id: 'node-parent-corp', name: 'Parent Corp', column: 1, properties: { tax: 'Parent Corp Tax Insight', benefits: 'Full Executive Perks', security: 'All Calls (FIF Access Insight)', clearance: 'Executive' } },
    { id: 'node-company-1', name: 'Company 1', column: 1, properties: { tax: 'Company 1 Local Tax Insight', benefits: 'Standard Corporate Package', security: 'Segmented Data Logins', clearance: 'Manager' } },
    
    // Column 2: Division
    { id: 'node-division', name: 'Division', column: 2, properties: { tax: 'Corporate Wage Compliance', benefits: 'Extended Medical Package', security: 'Regional Operations Audit', clearance: 'Manager' } },
    { id: 'node-operations', name: 'Operations', column: 2, properties: { tax: 'Ops Compliance Stack 1.2', benefits: 'Standard Health Perks', security: 'Operations Log Access', clearance: 'Manager' } },
    { id: 'node-shared-services', name: 'Shared Services', column: 2, properties: { tax: 'Services Share Compliance', benefits: 'Health + Gym Allowance', security: 'Shared Ledger Access', clearance: 'Manager' } },
    
    // Column 3: Hub / Pod
    { id: 'node-hub-pod', name: 'Hub / Pod', column: 3, properties: { tax: 'Base Station Regulation', benefits: 'Remote Allowance Group', security: 'Cluster Level Logs', clearance: 'Associate' } },
    { id: 'node-laguna-hub', name: 'Laguna Hub', column: 3, properties: { tax: 'Laguna Branch Tax Stack', benefits: 'Laguna Shuttle Service', security: 'Laguna Site Logins', clearance: 'Associate' } },
    { id: 'node-manila-pod', name: 'Manila Pod', column: 3, properties: { tax: 'Manila Site Tax Blueprint', benefits: 'Manila Site Perks', security: 'Manila Core Access', clearance: 'Associate' } },
    
    // Column 4: Position
    { 
        id: 'node-senior-hr-assoc', 
        name: 'Senior HR Associate (3.5)', 
        column: 4, 
        properties: { 
            tax: 'PIN Tax & Compliance Insight', 
            benefits: '15 FYL (1.5 FIL Benefit Insight)', 
            security: 'All Calls (FIF Access Insight)', 
            clearance: 'Executive'
        },
        subitems: ['Salary Maint', 'Santa Maria', 'Senior HR', 'Benefits']
    },
    { 
        id: 'node-oa-specialist', 
        name: 'OA Specialist', 
        column: 4, 
        properties: { 
            tax: 'OA Wage Tax Compliance', 
            benefits: 'Operations Allowance Tier 1', 
            security: 'Operations Basic Scope', 
            clearance: 'Associate'
        },
        subitems: ['Ticket Handling', 'Quality Audit']
    },
    { 
        id: 'node-qa-role', 
        name: 'QA ROLE', 
        column: 4, 
        properties: { 
            tax: 'QA Wage Regulation', 
            benefits: 'QA Health Bundle', 
            security: 'System Testing Access', 
            clearance: 'Restricted'
        },
        subitems: ['Run Test Scripts', 'Log Defect Tracking']
    },
    // Column 5: Team Column
    {
        id: 'node-hr-ops-team',
        name: 'HR Operations Team',
        column: 5,
        properties: {
            tax: 'HR Operations Wage Compliance',
            benefits: 'Team Performance Allowance',
            security: 'HR Ops Shared Space Access',
            clearance: 'Associate'
        },
        teammembers: ['Guian Santos', 'Alice Smith', 'Bob Jones']
    },
    {
        id: 'node-qa-team',
        name: 'QA Testing Team',
        column: 5,
        properties: {
            tax: 'QA Wage Regulation',
            benefits: 'QA Shared Wellness Bundle',
            security: 'QA Core Team Workspace',
            clearance: 'Restricted'
        },
        teammembers: ['Charlie Brown', 'Dana Scully']
    }
];

const DEMO_CONNECTIONS = [
    { from: 'node-company-1', to: 'node-operations' },
    { from: 'node-shared-services', to: 'node-laguna-hub' },
    { from: 'node-laguna-hub', to: 'node-senior-hr-assoc' },
    { from: 'node-manila-pod', to: 'node-oa-specialist' },
    { from: 'node-oa-specialist', to: 'node-qa-role' },
    { from: 'node-senior-hr-assoc', to: 'node-hr-ops-team' },
    { from: 'node-qa-role', to: 'node-qa-team' }
];

const state = {
    activeStep: 1, // Steps 1 to 6
    organization: {
        name: 'Respawn Logic',
        domain: 'respawn.io',
        industry: 'technology',
        size: '1-50'
    },
    // Nodes representing the org structure (Initial state starts blank)
    nodes: [],
    // Hierarchy connections between nodes
    connections: [],
    selectedNodeId: null,
    connectingNodeId: null,
    currentUser: {
        firstName: '',
        lastName: '',
        positionNodeId: ''
    },
    tempCredentials: {
        email: '',
        password: ''
    },
    sandboxAccounts: [],
    loggedIn: false,
    activeTab: 'overview',
    impersonatedNodeId: null,
    zoomScale: 1.0,
    draggedConnection: null,
    columnNames: {
        1: '[ COLUMN 1 ]',
        2: '[ COLUMN 2 ]',
        3: '[ COLUMN 3 ]',
        4: '[ COLUMN 4 ]',
        5: '[ COLUMN 5 ]'
    },
    columnTypes: {
        1: 'company',
        2: 'division',
        3: 'hub',
        4: 'position',
        5: 'team'
    }
};

// --------------------------------------------------------------------------
// 2. DOM ELEMENTS CACHE
// --------------------------------------------------------------------------
const DOM = {
    steps: {
        orgCreate: document.getElementById('step-org-create'),
        canvas: document.getElementById('step-org-canvas'),
        userPosition: document.getElementById('step-user-position'),
        credentials: document.getElementById('step-credentials'),
        login: document.getElementById('step-login'),
        dashboard: document.getElementById('step-dashboard')
    },
    orgForm: document.getElementById('org-create-form'),
    orgNameInput: document.getElementById('org-name'),
    orgDomainInput: document.getElementById('org-domain'),
    orgIndustrySelect: document.getElementById('org-industry'),
    orgSizeSelect: document.getElementById('org-size'),
    
    // Canvas elements — now managed by hris-canvas-engine.js (null-safe)
    canvasBoard: null,
    canvasSvg: null,
    columnContainers: {},
    propertiesSidebar: null,
    propertiesPlaceholder: null,
    propertiesContent: null,
    propNameInput: null,
    positionExtraFieldsSection: null,
    propEmpNumberInput: null,
    propJobPositionInput: null,
    positionItemsSection: null,
    positionSubItemsList: null,
    newSubItemInput: null,
    ghostAutocomplete: null,
    aiGenerateBtn: null,
    addSubItemBtn: null,
    canvasSaveBtn: null,
    canvasCancelBtn: null,
    
    // Team Column elements — managed by HRIS engine
    teamItemsSection: null,
    teamMembersList: null,
    newTeamMemberInput: null,
    addTeamMemberBtn: null,
    
    // User Profile Step
    userPositionForm: document.getElementById('user-position-form'),
    userFirstnameInput: document.getElementById('user-firstname'),
    userLastnameInput: document.getElementById('user-lastname'),
    userPositionSelect: document.getElementById('user-position-select'),
    
    // Credentials Pop-up
    tempEmailSpan: document.getElementById('temp-email'),
    tempPasswordSpan: document.getElementById('temp-password'),
    credentialsLoginBtn: document.getElementById('credentials-login-btn'),
    downloadAccountsCsvBtn: document.getElementById('download-accounts-csv-btn'),
    toggleAccountsListBtn: document.getElementById('toggle-accounts-list-btn'),
    accountsListContainer: document.getElementById('accounts-list-container'),
    sandboxAccountsTbody: document.getElementById('sandbox-accounts-tbody'),
    
    // Login Screen
    loginForm: document.getElementById('login-form'),
    loginEmailInput: document.getElementById('login-email'),
    loginPasswordInput: document.getElementById('login-password'),
    loginError: document.getElementById('login-error'),
    loginOrgTitle: document.getElementById('login-org-title'),
    
    // Dashboard Elements
    dashboardBrandName: document.getElementById('dashboard-brand-name'),
    dashboardBrandTitle: document.getElementById('dashboard-brand-name'),
    sidebarUserName: document.getElementById('sidebar-user-name'),
    sidebarUserRole: document.getElementById('sidebar-user-role'),
    sidebarUserAvatar: document.getElementById('sidebar-user-avatar'),
    topbarFirstname: document.getElementById('topbar-firstname'),
    sandboxRoleSelector: document.getElementById('sandbox-role-selector'),
    logoutBtn: document.getElementById('logout-btn'),
    
    // Metrics
    metricCompany: document.getElementById('dashboard-metric-company'),
    metricDivision: document.getElementById('dashboard-metric-division'),
    metricHub: document.getElementById('dashboard-metric-hub'),
    metricClearance: document.getElementById('dashboard-metric-clearance'),
    
    // Detail Blocks
    detailTax: document.getElementById('dashboard-detail-tax'),
    detailBenefits: document.getElementById('dashboard-detail-benefits'),
    detailSecurity: document.getElementById('dashboard-detail-security'),
    dashboardSubItems: document.getElementById('dashboard-sub-items'),
    hierarchyPathContainer: document.getElementById('hierarchy-path-container'),
    
    // Security tab specific matrix
    securityActiveLevel: document.getElementById('security-active-level'),
    secCardWrite: document.getElementById('sec-card-write'),
    secCardPayroll: document.getElementById('sec-card-payroll'),
    secCardBenefits: document.getElementById('sec-card-benefits'),
    secCardAudit: document.getElementById('sec-card-audit'),
    
    // Static fields in individual tabs
    directoryTableBody: document.getElementById('directory-table-body'),
    directoryCount: document.getElementById('directory-count'),
    benefitsPageValue: document.getElementById('benefits-page-value'),
    taxPageValue: document.getElementById('tax-page-value'),
    
    // Toast Notification
    toast: document.getElementById('toast'),
    toastMessage: document.querySelector('.toast-message'),
    
    // Zoom/Search/Batch — managed by HRIS engine
    zoomOutBtn: null,
    zoomInBtn: null,
    zoomResetBtn: null,
    zoomLevel: null,
    zoomSlider: null,
    canvasScaleWrapper: null,
    canvasSearchInput: null,
    downloadTemplateBtn: null,
    importBtn: null,
    clearCanvasBtn: null,
    addColumnBtn: null,
    importFileInput: null
};

// --------------------------------------------------------------------------
// HRIS CANVAS SAVE → APP BRIDGE
// Wire up the HRIS "Save Organizational Structure" button
// --------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    const hrisSaveBtn = document.getElementById('hris-canvas-save-btn');
    if (hrisSaveBtn) {
        hrisSaveBtn.addEventListener('click', async () => {
            // Validate at least one employee exists
            const employees = typeof hrisGetEmployees === 'function' ? hrisGetEmployees() : [];
            if (employees.length === 0) {
                showToast('Warning: Add at least one employee before saving.');
                return;
            }

            // Convert HRIS employees → app.js node/connection format
            if (typeof hrisConvertToAppNodes === 'function') {
                const converted = hrisConvertToAppNodes();
                state.nodes = converted.nodes;
                state.connections = converted.connections;
                state.columnNames = converted.columnNames;
                state.columnTypes = converted.columnTypes;
            }

            // Try to persist to database
            try {
                const payload = {
                    organization: state.organization,
                    nodes: state.nodes,
                    connections: state.connections,
                    columnNames: state.columnNames,
                    columnTypes: state.columnTypes
                };
                const response = await fetch('save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                showToast(data.success ? 'Organizational hierarchy saved!' : 'Saved to memory (server error).');
            } catch (err) {
                console.error('Save error:', err);
                showToast('Saved to memory (connection failed).');
            }

            navigateToStep(3);
        });
    }
});


// --------------------------------------------------------------------------
// 3. CORE ROUTING / NAVIGATION
// --------------------------------------------------------------------------
function navigateToStep(stepNumber) {
    state.activeStep = stepNumber;
    
    // Hide all onboarding steps
    Object.values(DOM.steps).forEach(stepElement => {
        stepElement.classList.remove('active');
    });
    
    // Display targeted step
    if (stepNumber === 1) {
        DOM.steps.orgCreate.classList.add('active');
    } else if (stepNumber === 2) {
        DOM.steps.canvas.classList.add('active');
        // Initialize the HRIS canvas engine (safe to call multiple times)
        if (typeof hrisInitCanvas === 'function') {
            hrisInitCanvas(state.organization.name);
        }
    } else if (stepNumber === 3) {
        DOM.steps.userPosition.classList.add('active');
        populateUserPositionsDropdown();
    } else if (stepNumber === 4) {
        DOM.steps.credentials.classList.add('active');
        renderCredentialsScreen();
    } else if (stepNumber === 5) {
        DOM.steps.login.classList.add('active');
        if (DOM.loginOrgTitle) {
            if (state.organization.name.toLowerCase() === 'respawn logic') {
                DOM.loginOrgTitle.innerText = 'RESPAWN';
                const subtitle = document.getElementById('login-org-subtitle');
                if (subtitle) subtitle.innerText = 'LOGIC';
            } else {
                DOM.loginOrgTitle.innerText = state.organization.name;
                const subtitle = document.getElementById('login-org-subtitle');
                if (subtitle) subtitle.innerText = '';
            }
        }
        // Autofill credentials helper
        DOM.loginEmailInput.value = state.tempCredentials.email;
        DOM.loginPasswordInput.value = state.tempCredentials.password;
    } else if (stepNumber === 6) {
        DOM.steps.dashboard.classList.add('active');
        initializeDashboard();
    }
}

// Show Toast message
function showToast(message, duration = 3000) {
    DOM.toastMessage.innerText = message;
    DOM.toast.classList.add('show');
    setTimeout(() => {
        DOM.toast.classList.remove('show');
    }, duration);
}

// --------------------------------------------------------------------------
// 4. STEP 1: ORGANIZATION CREATION LOGIC
// --------------------------------------------------------------------------
DOM.orgForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = DOM.orgNameInput.value.trim();
    const domain = DOM.orgDomainInput.value.trim().toLowerCase();
    const industry = DOM.orgIndustrySelect.value;
    const size = DOM.orgSizeSelect.value;
    
    try {
        const response = await fetch(`load.php?domain=${encodeURIComponent(domain)}`);
        const data = await response.json();
        
        if (data.found) {
            state.organization = data.organization;
            state.nodes = data.nodes || [];
            state.connections = data.connections || [];
            state.columnNames = data.columnNames || {};
            state.columnTypes = data.columnTypes || {};
            syncColumnNames();
            showToast(`Loaded existing layout for domain: "${domain}"`);
        } else {
            state.organization.name = name;
            state.organization.domain = domain;
            state.organization.industry = industry;
            state.organization.size = size;
            
            state.nodes = [];
            state.connections = [];
            state.columnNames = {};
            state.columnTypes = {};
            showToast(`Organization "${name}" initialized!`);
        }
        navigateToStep(2);
    } catch (err) {
        console.error('Error loading configuration:', err);
        // Fallback to blank initialization
        state.organization.name = name;
        state.organization.domain = domain;
        state.organization.industry = industry;
        state.organization.size = size;
        state.nodes = [];
        state.connections = [];
        state.columnNames = {};
        state.columnTypes = {};
        showToast(`Organization "${name}" initialized (offline mode).`);
        navigateToStep(2);
    }
});

// --------------------------------------------------------------------------
// 5. STEP 2: INTERACTIVE ORG CANVAS BUILDER
// NOTE: Rendering is now handled by hris-canvas-engine.js
// These stubs exist to prevent errors from any remaining internal references.
// --------------------------------------------------------------------------
let draggedNodeId = null;

// Stub — actual rendering handled by hrisRenderCanvas() in hris-canvas-engine.js
function renderCanvas() {
    if (typeof hrisRenderCanvas === 'function') hrisRenderCanvas();
}

// Stub — actual connections drawn by hrisDrawConnections() in hris-canvas-engine.js
function drawConnections() {
    if (typeof hrisDrawConnections === 'function') hrisDrawConnections();
}

// Action to create root company node using Step 1 input
function initializeRootCompanyNode(columnType = 'company') {

    // Initialize Column 1 if there are no columns active
    if (Object.keys(state.columnNames).length === 0) {
        state.columnNames = {
            1: columnType === 'team' ? '[ TEAM COLUMN 1 ]' : '[ COMPANY COLUMN 1 ]'
        };
        state.columnTypes = {
            1: columnType
        };
        syncColumnNames();
    }
    
    const uniqueId = `node-root-${Date.now()}`;
    const newNode = {
        id: uniqueId,
        name: state.organization.name || 'My Company',
        column: 1,
        properties: {
            tax: `${state.organization.name || 'My Company'} Tax Stack`,
            benefits: columnType === 'team' ? 'Team Performance Allowance' : 'Standard Corporate Perks',
            security: columnType === 'team' ? 'HR Ops Shared Space Access' : 'All Access Logged',
            clearance: 'Executive'
        }
    };
    
    if (columnType === 'team') {
        newNode.teammembers = ['Team Lead'];
    }
    
    state.nodes.push(newNode);
    showToast(`Initialized root ${columnType === 'team' ? 'Team' : 'Company'} node: "${newNode.name}"`);
    renderCanvas();
    selectNode(uniqueId);
    setTimeout(drawConnections, 50);
}

// Action to load full demo org structure
function loadDemoHierarchyData() {
    // Restore default columns
    state.columnNames = {
        1: '[ COLUMN 1 ]',
        2: '[ COLUMN 2 ]',
        3: '[ COLUMN 3 ]',
        4: '[ COLUMN 4 ]',
        5: '[ COLUMN 5 ]'
    };
    state.columnTypes = {
        1: 'company',
        2: 'division',
        3: 'hub',
        4: 'position',
        5: 'team'
    };
    syncColumnNames();
    
    state.nodes = JSON.parse(JSON.stringify(DEMO_NODES));
    state.connections = JSON.parse(JSON.stringify(DEMO_CONNECTIONS));
    
    showToast('Loaded demo organization hierarchy!');
    renderCanvas();
    setTimeout(drawConnections, 100);
}

// Create Card DOM nodes
function createNodeCardElement(node) {
    const card = document.createElement('div');
    card.className = `node-card ${state.selectedNodeId === node.id ? 'selected' : ''}`;
    card.setAttribute('data-id', node.id);
    card.setAttribute('data-type', getNodeTypeByColumn(node.column));
    card.draggable = true;

    // Build Node controls & handles
    const header = document.createElement('div');
    header.className = 'node-header';
    
    const titleInput = document.createElement('input');
    titleInput.type = 'text';
    titleInput.className = 'node-title-input';
    titleInput.value = node.name;
    titleInput.placeholder = 'Name element...';
    
    // Listen for name updates
    titleInput.addEventListener('input', (e) => {
        node.name = e.target.value;
        // If it's selected, update properties header details in sidebar
        if (state.selectedNodeId === node.id) {
            DOM.propNameInput.value = node.name;
        }
    });
    
    // Lock connection points
    titleInput.addEventListener('mousedown', (e) => e.stopPropagation()); // Avoid dragging when selecting text
    
    const actions = document.createElement('div');
    actions.className = 'node-actions';
    
    // Connection action (only if Col < maxCol)
    const maxCol = Math.max(...Object.keys(state.columnNames).map(Number));
    if (node.column < maxCol) {
        const connectBtn = document.createElement('button');
        connectBtn.className = `node-action-btn connect ${state.connectingNodeId === node.id ? 'connecting-active' : ''}`;
        connectBtn.title = 'Link to child node';
        connectBtn.innerHTML = '<i class="fa-solid fa-link"></i>';
        connectBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleConnectionMode(node.id);
        });
        actions.appendChild(connectBtn);
    }
    
    // Delete action
    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'node-action-btn delete';
    deleteBtn.title = 'Delete Node';
    deleteBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
    deleteBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteNode(node.id);
    });
    
    actions.appendChild(deleteBtn);
    
    // Collapse/Expand toggle button (only if Col < maxCol and has report descendants)
    const hasChildren = state.connections.some(c => c.from === node.id);
    if (node.column < maxCol && hasChildren) {
        const collapseBtn = document.createElement('button');
        collapseBtn.className = `node-action-btn collapse-toggle ${node.collapsed ? 'collapsed' : ''}`;
        collapseBtn.title = node.collapsed ? 'Expand branch' : 'Collapse branch';
        collapseBtn.innerHTML = node.collapsed ? '<i class="fa-solid fa-plus-circle"></i>' : '<i class="fa-solid fa-minus-circle"></i>';
        collapseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            node.collapsed = !node.collapsed;
            renderCanvas();
            drawConnections();
        });
        actions.appendChild(collapseBtn);
    }
    
    header.appendChild(titleInput);
    
    // Add Team Node Badge and Delete Members Button right beside it
    if (getNodeTypeByColumn(node.column) === 'team') {
        const badgeContainer = document.createElement('div');
        badgeContainer.style.cssText = 'display: flex; align-items: center; gap: 4px; flex-shrink: 0; margin-left: 4px;';
        
        const badge = document.createElement('span');
        badge.innerText = 'Team Node';
        badge.style.cssText = `
            font-size: 8px;
            padding: 2px 6px;
            background: rgba(236, 72, 153, 0.15);
            color: var(--accent-pink);
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        `;
        
        const deleteMembersBtn = document.createElement('button');
        deleteMembersBtn.className = 'node-action-btn delete-team-members';
        deleteMembersBtn.title = 'Delete All Team Members';
        deleteMembersBtn.innerHTML = '<i class="fa-solid fa-trash" style="font-size: 8px; color: rgba(239, 68, 68, 0.8);"></i>';
        deleteMembersBtn.style.cssText = 'background: transparent; border: none; cursor: pointer; padding: 2px; display: inline-flex; align-items: center; justify-content: center;';
        deleteMembersBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (confirm(`Delete all team members inside ${node.name}?`)) {
                node.teammembers = [];
                renderCanvas();
                if (state.selectedNodeId === node.id) {
                    renderTeamMembersList(node);
                }
            }
        });
        
        badgeContainer.appendChild(badge);
        badgeContainer.appendChild(deleteMembersBtn);
        header.appendChild(badgeContainer);
    }
    
    header.appendChild(actions);
    card.appendChild(header);
    
    // Previews panel contents in nodes
    const previewList = document.createElement('ul');
    previewList.className = 'node-preview-list';
    updateNodeCardPreviews(node, previewList);
    card.appendChild(previewList);
    
    // Left target connector ports
    const minCol = Math.min(...Object.keys(state.columnNames).map(Number));
    if (node.column > minCol) {
        const leftAnchor = document.createElement('div');
        leftAnchor.className = 'connector-anchor left';
        leftAnchor.addEventListener('click', (e) => {
            e.stopPropagation();
            handleAnchorClick(node.id, 'left');
        });
        card.appendChild(leftAnchor);
    }
    
    // Right target connector ports (Columns 1 to maxCol-1)
    if (node.column < maxCol) {
        const rightAnchor = document.createElement('div');
        rightAnchor.className = 'connector-anchor right';
        
        rightAnchor.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            e.preventDefault();
            
            const wrapperRect = DOM.canvasScaleWrapper.getBoundingClientRect();
            const anchorRect = rightAnchor.getBoundingClientRect();
            const startX = ((anchorRect.left + anchorRect.right) / 2 - wrapperRect.left) / state.zoomScale;
            const startY = ((anchorRect.top + anchorRect.bottom) / 2 - wrapperRect.top) / state.zoomScale;
            
            startConnectionDrag(node.id, startX, startY, e.clientX, e.clientY, false);
        });
        
        card.appendChild(rightAnchor);
    }
    
    // Click card to open sidebar properties panel
    card.addEventListener('click', () => {
        selectNode(node.id);
    });
    
    // DRAG AND DROP SETUP
    card.addEventListener('dragstart', (e) => {
        draggedNodeId = node.id;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    
    card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        draggedNodeId = null;
        drawConnections();
    });
    
    return card;
}

// Convert Col numbers to standard readable names
function getNodeTypeByColumn(colNum) {
    if (state.columnTypes && state.columnTypes[colNum]) {
        return state.columnTypes[colNum];
    }
    if (colNum >= 5) return 'team';
    if (colNum === 4) return 'position';
    if (colNum === 1) return 'company';
    if (colNum === 2) return 'division';
    return 'hub';
}

// Recursive helper to check if a node's parent or ancestor is collapsed
function isNodeVisible(nodeId) {
    const node = state.nodes.find(n => n.id === nodeId);
    if (!node) return false;
    const minCol = Math.min(...Object.keys(state.columnNames).map(Number));
    if (node.column === minCol) return true;
    
    const parentConn = state.connections.find(c => c.to === nodeId);
    if (!parentConn) return true; // Orphan elements are visible
    
    const parentNode = state.nodes.find(n => n.id === parentConn.from);
    if (!parentNode) return true;
    
    if (parentNode.collapsed) return false;
    return isNodeVisible(parentNode.id);
}

function updateNodeCardPreviews(node, previewList) {
    previewList.innerHTML = '';
    
    if (node.properties.clearance) {
        const itemSec = document.createElement('li');
        itemSec.className = 'node-preview-item';
        itemSec.innerHTML = `<i class="fa-solid fa-shield-halved"></i> ${node.properties.clearance}`;
        previewList.appendChild(itemSec);
    }
    if (node.properties.employee_number) {
        const itemEmp = document.createElement('li');
        itemEmp.className = 'node-preview-item';
        itemEmp.innerHTML = `<i class="fa-solid fa-id-card"></i> ID: ${node.properties.employee_number}`;
        previewList.appendChild(itemEmp);
    }
    if (node.properties.job_position) {
        const itemJob = document.createElement('li');
        itemJob.className = 'node-preview-item';
        itemJob.innerHTML = `<i class="fa-solid fa-briefcase"></i> Job: ${node.properties.job_position}`;
        previewList.appendChild(itemJob);
    }
    if (node.properties.benefits) {
        const itemBen = document.createElement('li');
        itemBen.className = 'node-preview-item';
        itemBen.innerHTML = `<i class="fa-solid fa-gift"></i> ${node.properties.benefits}`;
        previewList.appendChild(itemBen);
    }
    
    if (getNodeTypeByColumn(node.column) === 'team') {
        const memberCount = node.teammembers ? node.teammembers.length : 0;
        const itemTeam = document.createElement('li');
        itemTeam.className = 'node-preview-item';
        itemTeam.innerHTML = `<i class="fa-solid fa-users"></i> ${memberCount} Team Members`;
        previewList.appendChild(itemTeam);
    }
    
    // Render a collapsed badge with reports count if collapsed
    if (node.collapsed) {
        const descCount = countDescendants(node.id);
        const collapseBadge = document.createElement('li');
        collapseBadge.className = 'node-preview-item collapsed-badge';
        collapseBadge.innerHTML = `<i class="fa-solid fa-users-viewfinder"></i> <span>+${descCount} reports hidden</span>`;
        previewList.appendChild(collapseBadge);
    }
}

// Count reports recursively under a parent node
function countDescendants(nodeId) {
    let count = 0;
    const children = state.connections.filter(c => c.from === nodeId).map(c => c.to);
    count += children.length;
    children.forEach(childId => {
        count += countDescendants(childId);
    });
    return count;
}

// --------------------------------------------------------------------------
// Drag and Drop reordering logic inside columns
// --------------------------------------------------------------------------
// Dragover reordering is bound dynamically to each nodes-container on renderCanvas()

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.node-card:not(.dragging)')];
    
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function reorderNodesInStateFromDOM(columnNum, container) {
    const cardOrder = [...container.querySelectorAll('.node-card')].map(c => c.getAttribute('data-id'));
    
    // Split unaffected nodes and nodes from this column
    const otherNodes = state.nodes.filter(n => n.column !== columnNum);
    const colNodes = state.nodes.filter(n => n.column === columnNum);
    
    // Sort column nodes matching visual DOM list order
    colNodes.sort((a, b) => cardOrder.indexOf(a.id) - cardOrder.indexOf(b.id));
    
    // Save to state
    state.nodes = [...otherNodes, ...colNodes];
}

// --------------------------------------------------------------------------
// Node additions & deletions
// --------------------------------------------------------------------------
// Add column buttons click event is bound dynamically on renderCanvas()

function addNewNodeInColumn(colNum) {
    const uniqueId = `node-custom-${Date.now()}`;
    const nodeType = getNodeTypeByColumn(colNum);
    
    let defaultName = 'New Node';
    let clearance = 'Associate';
    
    if (nodeType === 'company') {
        defaultName = 'New Company';
        clearance = 'Executive';
    } else if (nodeType === 'division') {
        defaultName = 'New Division';
        clearance = 'Manager';
    } else if (nodeType === 'position') {
        defaultName = 'New Position';
        clearance = 'Associate';
    } else if (nodeType === 'team') {
        defaultName = 'New Team';
        clearance = 'Associate';
    } else {
        defaultName = 'New Hub/Pod';
        clearance = 'Associate';
    }
    
    const newNode = {
        id: uniqueId,
        name: defaultName,
        column: colNum,
        properties: {
            tax: 'PIN Tax & Compliance Insight',
            benefits: 'Standard Benefits Tier',
            security: 'All Actions Logged',
            clearance: clearance
        }
    };
    
    if (nodeType === 'position') {
        newNode.subitems = ['Self-Service Core'];
    }
    if (nodeType === 'team') {
        newNode.teammembers = ['Team Lead'];
    }
    
    state.nodes.push(newNode);
    renderCanvas();
    selectNode(uniqueId);
    
    // Draw connections immediately after rendering DOM structure
    setTimeout(drawConnections, 50);
    showToast(`Added element to column ${colNum}`);
}

function deleteColumn(colNum) {
    const colName = cleanColumnName(state.columnNames[colNum] || `Column ${colNum}`);
    if (confirm(`Are you sure you want to delete the column "${colName}"? This will delete all nodes inside it and shift subsequent columns.`)) {
        // 1. Delete all nodes in this column
        const nodesInCol = state.nodes.filter(n => n.column === colNum);
        const nodeIdsInCol = new Set(nodesInCol.map(n => n.id));
        
        state.nodes = state.nodes.filter(n => n.column !== colNum);
        
        // If selectedNodeId was one of the deleted nodes, clear selection
        if (state.selectedNodeId && nodeIdsInCol.has(state.selectedNodeId)) {
            state.selectedNodeId = null;
            closeSidebar();
        }
        if (state.connectingNodeId && nodeIdsInCol.has(state.connectingNodeId)) {
            state.connectingNodeId = null;
        }

        // 2. Shift subsequent columns left in state.columnNames and state.columnTypes
        const colNums = Object.keys(state.columnNames).map(Number).sort((a, b) => a - b);
        const maxCol = colNums.length > 0 ? Math.max(...colNums) : 0;

        // Update node columns for columns greater than colNum
        state.nodes.forEach(n => {
            if (n.column > colNum) {
                n.column = n.column - 1;
            }
        });

        // Shift columnNames and columnTypes mapping in state
        for (let c = colNum + 1; c <= maxCol; c++) {
            state.columnNames[c - 1] = state.columnNames[c];
            state.columnTypes[c - 1] = state.columnTypes[c];
        }
        // Delete the last column entries as they shifted left
        delete state.columnNames[maxCol];
        delete state.columnTypes[maxCol];

        // 3. Filter connections to remove orphaned links
        const remainingNodeIds = new Set(state.nodes.map(n => n.id));
        state.connections = state.connections.filter(c => remainingNodeIds.has(c.from) && remainingNodeIds.has(c.to));

        showToast(`Deleted column "${colName}" and shifted subsequent columns.`);
        
        // Sync & Render
        syncColumnNames();
        renderCanvas();
        drawConnections();
    }
}

function deleteNode(nodeId) {
    // Delete node object from state
    state.nodes = state.nodes.filter(n => n.id !== nodeId);
    
    // Filter and delete connections linking to this node
    state.connections = state.connections.filter(c => c.from !== nodeId && c.to !== nodeId);
    
    // Clear selection state
    if (state.selectedNodeId === nodeId) {
        state.selectedNodeId = null;
        closeSidebar();
    }
    
    if (state.connectingNodeId === nodeId) {
        state.connectingNodeId = null;
    }
    
    renderCanvas();
    drawConnections();
    showToast('Deleted node and its links.');
}

// Re-draw overlay on resize
window.addEventListener('resize', drawConnections);

// Track window scroll/drag updates (guarded — canvas board managed by HRIS engine)
if (DOM.canvasBoard) DOM.canvasBoard.addEventListener('scroll', drawConnections);


// --------------------------------------------------------------------------
// Link Creation Flow & Clicks
// --------------------------------------------------------------------------
function toggleConnectionMode(nodeId) {
    // If already in connection mode for this node, cancel
    if (state.connectingNodeId === nodeId) {
        cancelConnectionMode();
        return;
    }
    
    // Enter connection mode
    state.connectingNodeId = nodeId;
    const node = state.nodes.find(n => n.id === nodeId);
    
    // Highlight UI and print feedback toast
    renderCanvas();
    drawConnections();
    showToast(`Click a target node in Column ${node.column + 1} to link.`);
    
    // Listen for cancellation click outside
    document.addEventListener('click', handleConnectionModeCancelOutside);
}

function cancelConnectionMode() {
    state.connectingNodeId = null;
    document.querySelectorAll('.connector-anchor').forEach(el => {
        el.classList.remove('connecting-target');
    });
    renderCanvas();
    drawConnections();
    document.removeEventListener('click', handleConnectionModeCancelOutside);
}

function handleConnectionModeCancelOutside(e) {
    // If they clicked something other than a card or connect button, cancel
    if (!e.target.closest('.node-card') && !e.target.closest('.add-node-column-btn')) {
        cancelConnectionMode();
    }
}

function handleAnchorClick(nodeId, side) {
    if (!state.connectingNodeId) {
        // If not in connection mode, check if we can unlink
        if (side === 'left') {
            const hasConn = state.connections.find(c => c.to === nodeId);
            if (hasConn) {
                state.connections = state.connections.filter(c => c.to !== nodeId);
                showToast('Connection unlinked.');
                renderCanvas();
                drawConnections();
                return;
            }
        }
        // Otherwise do normal select
        selectNode(nodeId);
        return;
    }
    
    const sourceNode = state.nodes.find(n => n.id === state.connectingNodeId);
    const targetNode = state.nodes.find(n => n.id === nodeId);
    
    if (side === 'left' && targetNode.column === sourceNode.column + 1) {
        // Establish connection!
        createConnection(sourceNode.id, targetNode.id);
        cancelConnectionMode();
    } else {
        // Invalid target
        showToast('Invalid target connection. Link nodes left-to-right into adjacent columns only.');
    }
}

function createConnection(fromId, toId) {
    // A child node can only have at most one parent connection (c.to !== toId)
    // But allow a parent to have multiple outgoing child connections!
    state.connections = state.connections.filter(c => c.to !== toId);
    
    state.connections.push({ from: fromId, to: toId });
    showToast('Link successfully established!');
    renderCanvas();
    drawConnections();
}

// --------------------------------------------------------------------------
// Element Selection & Properties panel Updates
// NOTE: These functions are no-ops now since Step 2 is handled by HRIS engine.
// They remain to satisfy internal calls from other old functions.
// --------------------------------------------------------------------------
function selectNode(nodeId) {
    state.selectedNodeId = nodeId;
    // Selection visual handled by HRIS engine
}

function closeSidebar() {
    // Sidebar handled by HRIS engine — no-op
}


// Listen to input changes in the Properties sidebar fields
// All null-guarded since Step 2 DOM is managed by HRIS engine
if (DOM.propNameInput) DOM.propNameInput.addEventListener('input', (e) => {
    if (!state.selectedNodeId) return;
    const node = state.nodes.find(n => n.id === state.selectedNodeId);
    node.name = e.target.value;
    const inputEl = document.querySelector(`.node-card[data-id="${node.id}"] .node-title-input`);
    if (inputEl) inputEl.value = node.name;
});

if (DOM.propEmpNumberInput) DOM.propEmpNumberInput.addEventListener('input', (e) => {
    if (!state.selectedNodeId) return;
    const node = state.nodes.find(n => n.id === state.selectedNodeId);
    if (!node.properties) node.properties = {};
    node.properties.employee_number = e.target.value;
    const cardEl = document.querySelector(`.node-card[data-id="${node.id}"]`);
    if (cardEl) {
        const previewList = cardEl.querySelector('.node-preview-list');
        if (previewList) updateNodeCardPreviews(node, previewList);
    }
});

if (DOM.propJobPositionInput) {
    DOM.propJobPositionInput.addEventListener('input', (e) => {
        if (!state.selectedNodeId) return;
        const node = state.nodes.find(n => n.id === state.selectedNodeId);
        if (!node.properties) node.properties = {};
        node.properties.job_position = e.target.value;
        
        const cardEl = document.querySelector(`.node-card[data-id="${node.id}"]`);
        if (cardEl) {
            const previewList = cardEl.querySelector('.node-preview-list');
            if (previewList) {
                updateNodeCardPreviews(node, previewList);
            }
        }
    });
}

// Position responsibilities helper list
function renderSubItemsList(node) {
    if (!DOM.positionSubItemsList) return;
    DOM.positionSubItemsList.innerHTML = '';
    if (!node.subitems) node.subitems = [];
    
    node.subitems.forEach((item, index) => {
        const li = document.createElement('li');
        li.className = 'prop-pill';
        li.style.width = '100%';
        li.style.display = 'flex';
        li.style.alignItems = 'center';
        li.style.justifyContent = 'space-between';
        li.style.gap = '8px';
        
        li.innerHTML = `
            <input type="text" class="prop-pill-input" value="${item.replace(/"/g, '&quot;')}" style="background: transparent; border: none; color: var(--text-primary); font-family: var(--font-body); font-size: 12px; flex-grow: 1; outline: none; padding: 2px 4px;">
            <button class="prop-pill-delete" data-index="${index}" style="background: transparent; border: none; color: var(--text-muted); cursor: pointer; flex-shrink: 0; transition: color 0.15s;"><i class="fa-solid fa-xmark"></i></button>
        `;
        
        // Listen to changes to save edited responsibilities back to state in real-time
        const inputEl = li.querySelector('.prop-pill-input');
        inputEl.addEventListener('input', (e) => {
            node.subitems[index] = e.target.value;
        });
        
        // Block canvas drag keydown events when editing responsibilities
        inputEl.addEventListener('keydown', (e) => {
            e.stopPropagation();
        });
        
        const deleteBtn = li.querySelector('.prop-pill-delete');
        deleteBtn.addEventListener('click', () => {
            node.subitems.splice(index, 1);
            renderSubItemsList(node);
        });
        
        // Hover red delete effect
        deleteBtn.addEventListener('mouseenter', () => {
            deleteBtn.style.color = 'var(--accent-red)';
        });
        deleteBtn.addEventListener('mouseleave', () => {
            deleteBtn.style.color = 'var(--text-muted)';
        });
        
        DOM.positionSubItemsList.appendChild(li);
    });
}

const responsibilitiesSuggestions = [
    "Salary Maintenance",
    "Benefits Management",
    "Sourcing & Recruiting",
    "Onboarding Operations",
    "Performance Review",
    "Payroll Administration",
    "Employee Relations",
    "Policy Enforcement",
    "Quality Audit",
    "Ticket Handling",
    "Run Test Scripts",
    "Log Defect Tracking",
    "Training & Development",
    "Compliance Auditing",
    "Conflict Resolution",
    "HR System Maintenance"
];

// Predictive text suggestions logic
if (DOM.newSubItemInput) {
    DOM.newSubItemInput.addEventListener('input', (e) => {
        const val = e.target.value;
        if (!val) {
            if (DOM.ghostAutocomplete) DOM.ghostAutocomplete.innerHTML = '';
            return;
        }

        const match = responsibilitiesSuggestions.find(s => s.toLowerCase().startsWith(val.toLowerCase()));
        if (match) {
            const typedPart = val;
            const remainingPart = match.slice(val.length);
            if (DOM.ghostAutocomplete) DOM.ghostAutocomplete.innerHTML = `<span style="color: transparent; opacity: 0;">${typedPart}</span>${remainingPart}`;
        } else {
            if (DOM.ghostAutocomplete) DOM.ghostAutocomplete.innerHTML = '';
        }
    });

    DOM.newSubItemInput.addEventListener('blur', () => {
        // Clear autocomplete overlay when input loses focus
        if (DOM.ghostAutocomplete) DOM.ghostAutocomplete.innerHTML = '';
    });
}

if (DOM.addSubItemBtn) {
    DOM.addSubItemBtn.addEventListener('click', () => {
        const val = DOM.newSubItemInput ? DOM.newSubItemInput.value.trim() : '';
        if (!val || !state.selectedNodeId) return;
        
        const node = state.nodes.find(n => n.id === state.selectedNodeId);
        if (!node.subitems) node.subitems = [];
        
        node.subitems.push(val);
        if (DOM.newSubItemInput) DOM.newSubItemInput.value = '';
        if (DOM.ghostAutocomplete) DOM.ghostAutocomplete.innerHTML = '';
        renderSubItemsList(node);
    });
}

if (DOM.newSubItemInput) {
    DOM.newSubItemInput.addEventListener('keydown', (e) => {
        const val = DOM.newSubItemInput.value;
        const ghostText = DOM.ghostAutocomplete ? DOM.ghostAutocomplete.innerText : '';
        
        if (e.key === 'Escape') {
            if (DOM.ghostAutocomplete) DOM.ghostAutocomplete.innerHTML = '';
        } else if (ghostText && (e.key === 'Tab' || e.key === 'ArrowRight')) {
            e.preventDefault();
            const match = responsibilitiesSuggestions.find(s => s.toLowerCase().startsWith(val.toLowerCase()));
            if (match) {
                DOM.newSubItemInput.value = match;
                if (DOM.ghostAutocomplete) DOM.ghostAutocomplete.innerHTML = '';
            }
        } else if (e.key === 'Enter') {
            const match = responsibilitiesSuggestions.find(s => s.toLowerCase().startsWith(val.toLowerCase()));
            if (DOM.ghostAutocomplete && DOM.ghostAutocomplete.innerHTML !== '' && match && val.toLowerCase() !== match.toLowerCase()) {
                e.preventDefault();
                DOM.newSubItemInput.value = match;
                DOM.ghostAutocomplete.innerHTML = '';
            } else {
                if (DOM.addSubItemBtn) DOM.addSubItemBtn.click();
            }
        }
    });
}

// AI Generate Sparkles Action
if (DOM.aiGenerateBtn) {
    DOM.aiGenerateBtn.addEventListener('click', () => {
        if (!state.selectedNodeId) return;
        const node = state.nodes.find(n => n.id === state.selectedNodeId);
        if (!node) return;
        
        // Generate 4 professional responsibilities using our local AI Copilot engine
        const generated = generateAIResponsibilities(node.name);
        
        // Initialize array if not present
        if (!node.subitems) node.subitems = [];
        
        // Push responsibilities and avoid duplicates
        generated.forEach(item => {
            if (!node.subitems.includes(item)) {
                node.subitems.push(item);
            }
        });
        
        // Re-render list and show success toast
        renderSubItemsList(node);
        showToast(`✨ AI Copilot generated ${generated.length} responsibilities for "${node.name}"!`);
    });
}

function generateAIResponsibilities(positionName) {
    const title = positionName.toLowerCase();
    
    const db = [
        {
            keys: ['dev', 'code', 'engineer', 'program', 'software', 'frontend', 'backend', 'fullstack', 'web'],
            tasks: [
                "Architect and write clean, maintainable code",
                "Perform peer code reviews and approve pull requests",
                "Optimize application load performance and accessibility",
                "Deploy CI/CD pipelines and manage cloud deployments",
                "Integrate third-party API solutions and databases"
            ]
        },
        {
            keys: ['qa', 'test', 'quality', 'bug', 'defect'],
            tasks: [
                "Design and maintain automated regression test suites",
                "Author comprehensive manual test case logs",
                "Document defect trackers and verify bug fixes",
                "Verify API endpoint responses and payload structures",
                "Conduct cross-browser and cross-platform UI validation"
            ]
        },
        {
            keys: ['design', 'ui', 'ux', 'art', 'creative', 'product designer', 'graphic'],
            tasks: [
                "Create high-fidelity interactive Figma wireframes",
                "Conduct user testing sessions and compile feedback",
                "Maintain and scale the organization design system",
                "Collaborate with developers to align CSS variables",
                "Conduct layout review audits prior to production releases"
            ]
        },
        {
            keys: ['hr', 'human', 'recruit', 'people', 'talent', 'onboard', 'culture'],
            tasks: [
                "Manage employee onboarding workflows and profiles",
                "Source talent pipelines and coordinate interviews",
                "Administer benefits packages and leave policies",
                "Lead performance review cycles and compliance reviews",
                "Enforce organizational policies and cultural initiatives"
            ]
        },
        {
            keys: ['payroll', 'tax', 'finance', 'account', 'audit', 'wage', 'bookkeeper'],
            tasks: [
                "Execute monthly payroll cycles and direct deposits",
                "Audit tax classification documents and compliance reports",
                "Process expense reimbursements and vendor invoices",
                "Reconcile bank ledgers and compile fiscal reports",
                "Advise executive teams on compensation structuring"
            ]
        },
        {
            keys: ['sales', 'market', 'growth', 'seo', 'content', 'advert', 'social'],
            tasks: [
                "Optimize search engine placement (SEO) and copy writing",
                "Track marketing campaign budgets and conversion audits",
                "Establish customer pipelines and close enterprise deals",
                "Conduct client presentations and draft proposal decks",
                "Monitor Google Analytics logs and conversion statistics"
            ]
        },
        {
            keys: ['ceo', 'cto', 'cfo', 'coo', 'vp', 'director', 'president', 'founder', 'chief'],
            tasks: [
                "Steer long-term company roadmap and strategic objectives",
                "Authorize high-budget resource expenditures and budgets",
                "Represent the startup in board and investor updates",
                "Mentor senior management and evaluate division heads",
                "Review key corporate policies and auditing records"
            ]
        },
        {
            keys: ['lead', 'manager', 'head', 'supervisor', 'scrum', 'coordinator'],
            tasks: [
                "Facilitate daily sprint standups and align sprint backlogs",
                "Unblock team dependencies and allocate deliverables",
                "Conduct 1-on-1 development check-ins with team members",
                "Report team milestones and velocities to department heads",
                "Maintain sprint documentation and roadmap schedules"
            ]
        }
    ];
    
    // Check key matches
    for (const entry of db) {
        if (entry.keys.some(k => title.includes(k))) {
            return shuffleArray([...entry.tasks]).slice(0, 4);
        }
    }
    
    // Default fallback generator based on position name
    return [
        `Oversee core initiatives relating to ${positionName} operations`,
        `Collaborate across team structures to align deliverables`,
        `Maintain professional documentation and execution metrics`,
        `Optimize internal efficiency and operational compliance stacks`
    ];
}

function shuffleArray(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

// --------------------------------------------------------------------------
// 6. STEP 3: REGISTER USER DETAILS & ASSIGN POSITION
// --------------------------------------------------------------------------
function populateUserPositionsDropdown() {
    DOM.userPositionSelect.innerHTML = '';
    const positions = state.nodes.filter(n => getNodeTypeByColumn(n.column) === 'position');
    
    positions.forEach(pos => {
        const option = document.createElement('option');
        option.value = pos.id;
        option.innerText = pos.name;
        DOM.userPositionSelect.appendChild(option);
    });
}

DOM.userPositionForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    state.currentUser.firstName = DOM.userFirstnameInput.value.trim();
    state.currentUser.lastName = DOM.userLastnameInput.value.trim();
    state.currentUser.positionNodeId = DOM.userPositionSelect.value;
    
    // Auto-generate credentials based on domain
    const formattedFirst = state.currentUser.firstName.toLowerCase().replace(/\s+/g, '');
    const formattedLast = state.currentUser.lastName.toLowerCase().replace(/\s+/g, '');
    const email = `${formattedFirst}.${formattedLast}@${state.organization.domain}`;
    state.tempCredentials.email = email;
    
    // Generate secure 8 character random alphanumeric password
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    state.tempCredentials.password = pass;
    
    // Send configuration data to populate employee_system database
    try {
        const payload = {
            currentUser: {
                firstName: state.currentUser.firstName,
                lastName: state.currentUser.lastName,
                email: email,
                password: pass,
                positionNodeId: state.currentUser.positionNodeId
            },
            organization: state.organization,
            nodes: state.nodes,
            connections: state.connections,
            columnTypes: state.columnTypes
        };
        
        const response = await fetch('create_sandbox.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (data.success) {
            state.sandboxAccounts = data.accounts || [];
            showToast('Sandbox environment configured successfully in database!');
            navigateToStep(4);
        } else {
            state.sandboxAccounts = [];
            showToast(`Error: ${data.error || 'Database sandbox setup failed.'}`);
        }
    } catch (err) {
        console.error('Error creating sandbox in database:', err);
        state.sandboxAccounts = [];
        showToast('Error: Failed to connect to sandbox database server.');
    }
});

// --------------------------------------------------------------------------
// 7. STEP 4: TEMPORARY CREDENTIALS POPUP DIALOG
// --------------------------------------------------------------------------
function renderCredentialsScreen() {
    DOM.tempEmailSpan.innerText = state.tempCredentials.email;
    DOM.tempPasswordSpan.innerText = state.tempCredentials.password;
    
    // Hide the collapsible list by default when Step 4 is shown
    if (DOM.accountsListContainer) {
        DOM.accountsListContainer.classList.add('hidden');
    }
    const icon = document.getElementById('toggle-accounts-icon');
    if (icon) {
        icon.style.transform = 'rotate(0deg)';
    }
    if (DOM.toggleAccountsListBtn) {
        const textSpan = DOM.toggleAccountsListBtn.querySelector('span');
        if (textSpan) textSpan.innerText = 'Show All Generated Accounts';
    }
    
    // Render visual table
    renderSandboxAccountsTable();
}

function renderSandboxAccountsTable() {
    if (!DOM.sandboxAccountsTbody) return;
    DOM.sandboxAccountsTbody.innerHTML = '';
    
    const accounts = state.sandboxAccounts || [];
    if (accounts.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="3" style="text-align: center; padding: 12px; color: var(--text-muted); font-size: 11px;">No accounts generated.</td>`;
        DOM.sandboxAccountsTbody.appendChild(tr);
        return;
    }
    
    accounts.forEach(acc => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid rgba(255, 255, 255, 0.05)';
        
        tr.innerHTML = `
            <td style="padding: 6px 4px;">
                <div style="font-weight: 500;">${escapeHtml(acc.full_name)}</div>
                <div style="font-size: 9px; color: #94a3b8;">${escapeHtml(acc.job_title)}</div>
            </td>
            <td style="padding: 6px 4px; font-family: monospace; font-size: 10px;">${escapeHtml(acc.email)}</td>
            <td style="padding: 6px 4px;">
                <div style="display: flex; align-items: center; gap: 4px;">
                    <span style="font-family: monospace; font-size: 10px; background: rgba(255,255,255,0.05); padding: 2px 4px; border-radius: 3px;">${escapeHtml(acc.password)}</span>
                    <button type="button" class="copy-small-btn" data-text="${escapeHtml(acc.password)}" style="background: transparent; border: none; color: #94a3b8; cursor: pointer; padding: 2px; font-size: 10px; transition: color 0.15s;" title="Copy Password"><i class="fa-solid fa-copy"></i></button>
                </div>
            </td>
        `;
        
        const copyBtn = tr.querySelector('.copy-small-btn');
        copyBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const text = copyBtn.getAttribute('data-text');
            navigator.clipboard.writeText(text).then(() => {
                showToast('Copied password!');
                copyBtn.innerHTML = '<i class="fa-solid fa-check" style="color: var(--accent-green)"></i>';
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i>';
                }, 1500);
            });
        });
        
        DOM.sandboxAccountsTbody.appendChild(tr);
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Setup Copy Clipboard Buttons
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const val = document.getElementById(targetId).innerText;
        
        navigator.clipboard.writeText(val).then(() => {
            showToast('Copied to clipboard!');
            // Brief visual click state
            btn.innerHTML = '<i class="fa-solid fa-check" style="color: var(--accent-green)"></i>';
            setTimeout(() => {
                btn.innerHTML = '<i class="fa-solid fa-copy"></i>';
            }, 1500);
        }).catch(err => {
            console.error('Copy failure: ', err);
        });
    });
});

DOM.credentialsLoginBtn.addEventListener('click', () => {
    navigateToStep(5);
});

// CSV download trigger
if (DOM.downloadAccountsCsvBtn) {
    DOM.downloadAccountsCsvBtn.addEventListener('click', () => {
        downloadSandboxAccountsCSV();
    });
}

// Collapsible account list trigger
if (DOM.toggleAccountsListBtn) {
    DOM.toggleAccountsListBtn.addEventListener('click', () => {
        const isHidden = DOM.accountsListContainer.classList.toggle('hidden');
        const icon = document.getElementById('toggle-accounts-icon');
        const textSpan = DOM.toggleAccountsListBtn.querySelector('span');
        
        if (isHidden) {
            if (icon) icon.style.transform = 'rotate(0deg)';
            if (textSpan) textSpan.innerText = 'Show All Generated Accounts';
        } else {
            if (icon) icon.style.transform = 'rotate(180deg)';
            if (textSpan) textSpan.innerText = 'Hide All Generated Accounts';
            // Scroll to the container so it's fully in view
            DOM.accountsListContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
}

function downloadSandboxAccountsCSV() {
    const accounts = state.sandboxAccounts || [];
    if (accounts.length === 0) {
        showToast('No accounts available for download.');
        return;
    }
    
    let csv = "\uFEFF"; // UTF-8 BOM for Excel support
    csv += "Full Name,Email Address,Temporary Password,Job Title,Department,Role,Immediate Supervisor\n";
    
    accounts.forEach(acc => {
        const row = [
            `"${(acc.full_name || '').replace(/"/g, '""')}"`,
            `"${(acc.email || '').replace(/"/g, '""')}"`,
            `"${(acc.password || '').replace(/"/g, '""')}"`,
            `"${(acc.job_title || '').replace(/"/g, '""')}"`,
            `"${(acc.department || '').replace(/"/g, '""')}"`,
            `"${(acc.role || '').replace(/"/g, '""')}"`,
            `"${(acc.immediate_supervisor || '').replace(/"/g, '""')}"`
        ].join(",");
        csv += row + "\n";
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `${state.organization.domain || 'org'}_sandbox_accounts.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('Credentials CSV downloaded successfully!');
}

// --------------------------------------------------------------------------
// 8. STEP 5: LOGIN PAGE VERIFICATION
// --------------------------------------------------------------------------
DOM.loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const emailInput = DOM.loginEmailInput.value.trim().toLowerCase();
    const passInput = DOM.loginPasswordInput.value.trim();
    
    if (emailInput === state.tempCredentials.email.toLowerCase() && passInput === state.tempCredentials.password) {
        state.loggedIn = true;
        // Default impersonation is user's selected position node
        state.impersonatedNodeId = state.currentUser.positionNodeId;
        
        showToast('Login successful! Welcome to the workspace.');
        navigateToStep(6);
    } else {
        DOM.loginError.classList.remove('hidden');
        setTimeout(() => {
            DOM.loginError.classList.add('hidden');
        }, 5000);
    }
});

// --------------------------------------------------------------------------
// 9. STEP 6: SANDBOX SYSTEM DASHBOARD SIMULATOR
// --------------------------------------------------------------------------

// Dashboard Tab management switcher
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', (e) => {
        e.preventDefault();
        const tab = item.getAttribute('data-tab');
        switchDashboardTab(tab);
    });
});

function switchDashboardTab(tabName) {
    state.activeTab = tabName;
    
    // Highlight sidebar active item
    document.querySelectorAll('.menu-item').forEach(el => {
        el.classList.remove('active');
        if (el.getAttribute('data-tab') === tabName) {
            el.classList.add('active');
        }
    });
    
    // Toggle active pages
    document.querySelectorAll('.dashboard-page').forEach(page => {
        page.classList.remove('active');
    });
    
    const targetPage = document.getElementById(`tab-${tabName}`);
    if (targetPage) {
        targetPage.classList.add('active');
    }
}

// Logout Action
DOM.logoutBtn.addEventListener('click', () => {
    if (confirm('Are you sure you want to log out of the sandbox session?')) {
        state.loggedIn = false;
        state.currentUser = { firstName: '', lastName: '', positionNodeId: '' };
        state.tempCredentials = { email: '', password: '' };
        DOM.loginEmailInput.value = '';
        DOM.loginPasswordInput.value = '';
        DOM.userFirstnameInput.value = '';
        DOM.userLastnameInput.value = '';
        navigateToStep(1);
    }
});

// Initialize Dashboard components
function initializeDashboard() {
    DOM.dashboardBrandName.innerText = state.organization.name;
    DOM.topbarFirstname.innerText = state.currentUser.firstName;
    
    // Set up user profile initials
    const initials = (state.currentUser.firstName.charAt(0) + state.currentUser.lastName.charAt(0)).toUpperCase();
    DOM.sidebarUserAvatar.innerText = initials;
    DOM.sidebarUserName.innerText = `${state.currentUser.firstName} ${state.currentUser.lastName}`;
    
    // Dynamically build Impersonator Dropdown containing all Column 4 Position nodes
    DOM.sandboxRoleSelector.innerHTML = '';
    const positions = state.nodes.filter(n => getNodeTypeByColumn(n.column) === 'position');
    
    positions.forEach(pos => {
        const option = document.createElement('option');
        option.value = pos.id;
        option.innerText = pos.name;
        // Default select target is current active role
        if (pos.id === state.impersonatedNodeId) option.selected = true;
        DOM.sandboxRoleSelector.appendChild(option);
    });
    
    // Connect selector change listener
    DOM.sandboxRoleSelector.addEventListener('change', (e) => {
        state.impersonatedNodeId = e.target.value;
        showToast(`Impersonating: ${state.nodes.find(n => n.id === state.impersonatedNodeId).name}`);
        syncDashboardWithActiveImpersonation();
    });
    
    // Force sync properties rendering
    syncDashboardWithActiveImpersonation();
}

// --------------------------------------------------------------------------
// Tree traversal engine to find active paths
// --------------------------------------------------------------------------
function traceHierarchyTreePath(positionId) {
    const path = [];
    let currentId = positionId;
    
    // Add position first
    const posNode = state.nodes.find(n => n.id === currentId);
    if (!posNode) return path;
    path.unshift(posNode);
    
    // Search parent in connections link loop
    for (let c = posNode.column - 1; c >= 1; c--) {
        const conn = state.connections.find(link => link.to === currentId);
        if (conn) {
            const parentNode = state.nodes.find(n => n.id === conn.from);
            if (parentNode) {
                path.unshift(parentNode);
                currentId = parentNode.id;
            } else {
                break;
            }
        } else {
            break;
        }
    }
    
    return path;
}

// Sync dashboard interface layout and details based on impersonated role
function syncDashboardWithActiveImpersonation() {
    const node = state.nodes.find(n => n.id === state.impersonatedNodeId);
    if (!node) return;
    
    // Update user sub role text
    DOM.sidebarUserRole.innerText = node.name;
    
    // Find active path to parent structures
    const path = traceHierarchyTreePath(node.id);
    
    // Extract metadata values
    const companyNode = path.find(n => getNodeTypeByColumn(n.column) === 'company');
    const divisionNode = path.find(n => getNodeTypeByColumn(n.column) === 'division');
    const hubNode = path.find(n => getNodeTypeByColumn(n.column) === 'hub');
    
    DOM.metricCompany.innerText = companyNode ? companyNode.name : 'Unmapped (Direct Report)';
    DOM.metricDivision.innerText = divisionNode ? divisionNode.name : 'Unmapped (Direct Report)';
    DOM.metricHub.innerText = hubNode ? hubNode.name : 'Unmapped (Direct Report)';

    // Update dynamic metric labels on dashboard based on custom column renames
    const labels = document.querySelectorAll('.metric-card .metric-label');
    if (labels.length >= 3) {
        labels[0].innerText = state.columnNames[1] ? `Assigned ${cleanColumnName(state.columnNames[1])}` : 'Assigned Company';
        labels[1].innerText = state.columnNames[2] ? cleanColumnName(state.columnNames[2]) : 'Division';
        labels[2].innerText = state.columnNames[3] ? cleanColumnName(state.columnNames[3]) : 'Hub/Pod';
    }
    
    const clearance = node.properties.clearance || 'Associate';
    DOM.metricClearance.innerText = clearance;
    
    // Display visual chain of command path
    DOM.hierarchyPathContainer.innerHTML = '';
    path.forEach((n, idx) => {
        const badge = document.createElement('div');
        const nType = getNodeTypeByColumn(n.column);
        badge.className = `flow-node ${nType}`;
        
        let iconHtml = '<i class="fa-solid fa-map-pin"></i>';
        if (nType === 'company') iconHtml = '<i class="fa-solid fa-building"></i>';
        else if (nType === 'division') iconHtml = '<i class="fa-solid fa-network-wired"></i>';
        else if (nType === 'position' || nType === 'team') iconHtml = '<i class="fa-solid fa-id-badge"></i>';
        
        badge.innerHTML = `${iconHtml} <span>${n.name}</span>`;
        DOM.hierarchyPathContainer.appendChild(badge);
        
        if (idx < path.length - 1) {
            const arrow = document.createElement('div');
            arrow.className = 'flow-arrow';
            arrow.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
            DOM.hierarchyPathContainer.appendChild(arrow);
        }
    });
    
    // Update detail text fields
    DOM.detailTax.innerText = node.properties.tax || 'No compliance stack mapped.';
    DOM.detailBenefits.innerText = node.properties.benefits || 'No perks profiles assigned.';
    DOM.detailSecurity.innerText = node.properties.security || 'Standard Security Clearances.';
    
    // Render checklist subitems
    DOM.dashboardSubItems.innerHTML = '';
    if (node.subitems && node.subitems.length > 0) {
        node.subitems.forEach(item => {
            const li = document.createElement('li');
            li.className = 'checklist-item';
            li.innerHTML = `
                <i class="fa-regular fa-square-check"></i>
                <span>${item}</span>
            `;
            DOM.dashboardSubItems.appendChild(li);
        });
    } else {
        DOM.dashboardSubItems.innerHTML = '<li class="text-secondary font-size-12">No specific tasks defined.</li>';
    }
    
    // Sync single tabs content
    DOM.benefitsPageValue.innerText = node.properties.benefits || 'No benefits profile configured.';
    DOM.taxPageValue.innerText = node.properties.tax || 'No tax compliance rules mapped.';
    
    // Redraw directory table records
    renderDirectoryTable();
    
    // Sync Security Matrix Permissions based on user system clearance level
    syncSecurityClearanceMatrix(clearance);
}

// --------------------------------------------------------------------------
// Security Level matrix configuration
// --------------------------------------------------------------------------
function syncSecurityClearanceMatrix(clearanceLevel) {
    DOM.securityActiveLevel.innerText = clearanceLevel;
    
    // Visual indicators state configuration
    const config = {
        'Executive': { write: true, payroll: true, benefits: true, audit: true },
        'Manager':   { write: false, payroll: true, benefits: true, audit: false },
        'Associate': { write: false, payroll: false, benefits: true, audit: false }, // partial benefits
        'Restricted':{ write: false, payroll: false, benefits: false, audit: false }
    };
    
    const rules = config[clearanceLevel] || config['Associate'];
    
    setMatrixCardState(DOM.secCardWrite, rules.write);
    setMatrixCardState(DOM.secCardPayroll, rules.payroll);
    setMatrixCardState(DOM.secCardBenefits, rules.benefits);
    setMatrixCardState(DOM.secCardAudit, rules.audit);
}

function setMatrixCardState(cardEl, isAllowed) {
    const statusText = cardEl.querySelector('.matrix-status');
    cardEl.classList.remove('allowed', 'denied');
    
    if (isAllowed) {
        cardEl.classList.add('allowed');
        statusText.innerText = 'GRANTED';
    } else {
        cardEl.classList.add('denied');
        statusText.innerText = 'RESTRICTED';
    }
}

// --------------------------------------------------------------------------
// Directory Tab elements renderer
// --------------------------------------------------------------------------
function renderDirectoryTable() {
    DOM.directoryTableBody.innerHTML = '';
    DOM.directoryCount.innerText = state.nodes.length;
    
    state.nodes.forEach(n => {
        const row = document.createElement('tr');
        
        // Visual text styling
        const nameGroup = `<div class="table-row-name-group">
            <span class="dot ${getNodeTypeByColumn(n.column)}"></span>
            <strong>${n.name}</strong>
        </div>`;
        
        const displayType = cleanColumnName(state.columnNames[n.column]);
        
        // Find parent if it exists
        const link = state.connections.find(c => c.to === n.id);
        let parentName = '-';
        if (link) {
            const pNode = state.nodes.find(parent => parent.id === link.from);
            if (pNode) parentName = pNode.name;
        }
        
        const clearance = n.properties.clearance || '-';
        
        row.innerHTML = `
            <td>${nameGroup}</td>
            <td>${displayType}</td>
            <td>${parentName}</td>
            <td><span class="badge ${clearance === 'Executive' || clearance === 'Manager' ? 'badge-primary' : 'btn-secondary'}">${clearance}</span></td>
        `;
        
        DOM.directoryTableBody.appendChild(row);
    });
}

// --------------------------------------------------------------------------
// Column Renaming and Zoom Control Helpers
// --------------------------------------------------------------------------
function cleanColumnName(name) {
    // Helper to strip out brackets e.g. "[ COLUMN 1: Company ]" -> "Company"
    // or "[ COLUMN 1 ]" -> "COLUMN 1"
    const match = name.match(/\[\s*COLUMN\s*\d+\s*:\s*(.*?)\s*\]/i);
    if (match) return match[1].trim();
    return name.replace(/[\[\]]/g, '').trim();
}

function syncColumnNames() {
    // Sync select option inputs for positions on Step 3
    populateUserPositionsDropdown();
}

function setZoom(scale) {
    state.zoomScale = Math.max(0.15, Math.min(2.0, scale));
    if (DOM.zoomLevel) DOM.zoomLevel.innerText = `${Math.round(state.zoomScale * 100)}%`;
    if (DOM.zoomSlider) DOM.zoomSlider.value = state.zoomScale;
    
    // Apply transform and adjust width/height relative to scale so it fills view
    if (DOM.canvasScaleWrapper) {
        DOM.canvasScaleWrapper.style.transform = `scale(${state.zoomScale})`;
        DOM.canvasScaleWrapper.style.width = 'max-content';
        DOM.canvasScaleWrapper.style.minWidth = `${100 / state.zoomScale}%`;
        DOM.canvasScaleWrapper.style.height = `${100 / state.zoomScale}%`;
    }
    
    // Re-draw connection lines immediately and after scaling transitions
    drawConnections();
    setTimeout(drawConnections, 100);
    setTimeout(drawConnections, 250);
}

// Drag-to-connect Mouse Event Handlers
function startConnectionDrag(nodeId, startX, startY, mouseX, mouseY, hadConnection = false) {
    state.draggedConnection = {
        fromNodeId: nodeId,
        startX: startX,
        startY: startY,
        currentX: startX,
        currentY: startY,
        mouseStartX: mouseX,
        mouseStartY: mouseY,
        hadConnection: hadConnection
    };
    
    // Highlight all valid target connector ports in the adjacent next column
    const activeNode = state.nodes.find(n => n.id === nodeId);
    if (activeNode) {
        state.nodes.forEach(n => {
            if (n.column === activeNode.column + 1) {
                const targetCard = document.querySelector(`.node-card[data-id="${n.id}"]`);
                if (targetCard) {
                    const leftAnchor = targetCard.querySelector('.connector-anchor.left');
                    if (leftAnchor) leftAnchor.classList.add('connecting-target');
                }
            }
        });
    }
    
    document.addEventListener('mousemove', onConnectionDragMove);
    document.addEventListener('mouseup', onConnectionDragEnd);
}

function onConnectionDragMove(e) {
    if (!state.draggedConnection) return;
    if (!DOM.canvasScaleWrapper) return;
    const wrapperRect = DOM.canvasScaleWrapper.getBoundingClientRect();
    state.draggedConnection.currentX = (e.clientX - wrapperRect.left) / state.zoomScale;
    state.draggedConnection.currentY = (e.clientY - wrapperRect.top) / state.zoomScale;
    drawConnections();
}

function onConnectionDragEnd(e) {
    if (!state.draggedConnection) return;
    
    document.removeEventListener('mousemove', onConnectionDragMove);
    document.removeEventListener('mouseup', onConnectionDragEnd);
    
    // Calculate mouse delta vector to detect click vs drag
    const dx = Math.abs(e.clientX - state.draggedConnection.mouseStartX);
    const dy = Math.abs(e.clientY - state.draggedConnection.mouseStartY);
    
    if (dx < 5 && dy < 5) {
        // Only enter connection mode on click if we didn't just unlink an existing connection
        if (!state.draggedConnection.hadConnection) {
            toggleConnectionMode(state.draggedConnection.fromNodeId);
        }
    } else {
        // Release was a drag: check target card to form links
        const targetCard = e.target.closest('.node-card');
        if (targetCard) {
            const toNodeId = targetCard.getAttribute('data-id');
            const fromNodeId = state.draggedConnection.fromNodeId;
            
            const fromNode = state.nodes.find(n => n.id === fromNodeId);
            const toNode = state.nodes.find(n => n.id === toNodeId);
            
            if (fromNode && toNode && toNode.column === fromNode.column + 1) {
                createConnection(fromNodeId, toNodeId);
            } else if (toNode && toNode.column !== fromNode.column + 1) {
                showToast('Connections can only be made to the adjacent column.');
            }
        }
    }
    
    state.draggedConnection = null;
    
    // Clear targets styling highlights
    document.querySelectorAll('.connector-anchor').forEach(el => {
        el.classList.remove('connecting-target');
    });
    
    renderCanvas();
    drawConnections();
}

// Column renaming listeners are bound dynamically on renderCanvas()
function setupColumnTitleListeners() {}

// Attach Zoom Buttons listeners
function setupZoomListeners() {
    if (DOM.zoomOutBtn) {
        DOM.zoomOutBtn.addEventListener('click', () => {
            setZoom(state.zoomScale - 0.1);
        });
    }
    if (DOM.zoomInBtn) {
        DOM.zoomInBtn.addEventListener('click', () => {
            setZoom(state.zoomScale + 0.1);
        });
    }
    if (DOM.zoomResetBtn) {
        DOM.zoomResetBtn.addEventListener('click', () => {
            setZoom(1.0);
        });
    }
    if (DOM.zoomSlider) {
        DOM.zoomSlider.addEventListener('input', (e) => {
            setZoom(parseFloat(e.target.value));
        });
    }
}

// Add live search input filter listener
function setupSearchFilterListener() {
    if (!DOM.canvasSearchInput) return;
    
    DOM.canvasSearchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim().toLowerCase();
        
        if (!query) {
            // Remove all dimming and matching styles
            document.querySelectorAll('.node-card').forEach(card => {
                card.classList.remove('search-match', 'search-dimmed');
            });
            return;
        }
        
        state.nodes.forEach(node => {
            const card = document.querySelector(`.node-card[data-id="${node.id}"]`);
            if (!card) return;
            
            const isMatch = node.name.toLowerCase().includes(query) || 
                            (node.properties && node.properties.clearance && node.properties.clearance.toLowerCase().includes(query)) ||
                            (node.subitems && node.subitems.some(item => item.toLowerCase().includes(query)));
            
            if (isMatch) {
                card.classList.add('search-match');
                card.classList.remove('search-dimmed');
            } else {
                card.classList.add('search-dimmed');
                card.classList.remove('search-match');
            }
        });
    });
}

// --------------------------------------------------------------------------
// CSV Batch Template & Import Engine
// --------------------------------------------------------------------------
function downloadCSVTemplate() {
    const colNums = Object.keys(state.columnNames).map(Number).sort((a,b)=>a-b);
    const maxCol = Math.max(...colNums);
    
    // Build the header and sample rows based on actual columns
    let csvContent = "Column,Name,ConnectedTo,Responsibilities,TeamMembers,EmployeeNumber,JobPosition\n";
    
    // Output sample rows matching each column
    colNums.forEach((colNum) => {
        const colName = cleanColumnName(state.columnNames[colNum]);
        if (colNum === 1) {
            csvContent += `${colName},Parent Corp,,,,,Executive\n`;
        } else if (colNum === 2) {
            csvContent += `${colName},Operations Division,Parent Corp,,,,\n`;
        } else if (colNum === 3) {
            csvContent += `${colName},Laguna Hub,Operations Division,,,,\n`;
        } else if (colNum === 4) {
            csvContent += `${colName},Senior HR Associate (3.5),Laguna Hub,"Salary Maintenance,Benefits Management,Sourcing",,EMP-1001,Senior HR Associate\n`;
            csvContent += `${colName},QA Specialist,Laguna Hub,"Run Test Scripts,Log Defect Tracking",,EMP-1002,QA Specialist\n`;
        } else if (colNum === 5) {
            csvContent += `${colName},HR Operations Team,Senior HR Associate (3.5),,"Guian Santos,Alice Smith,Bob Jones",,\n`;
            csvContent += `${colName},QA Testing Team,QA Specialist,,"Charlie Brown,Dana Scully",,\n`;
        } else {
            // intermediate columns
            csvContent += `${colName},Branch Hub ${colNum},Operations Division,,,,\n`;
        }
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", "respawn_logic_org_template.csv");
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('Template download started!');
}

// Custom CSV Parser that handles double quotes and commas
function parseCSV(text) {
    const lines = [];
    let row = [""];
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const char = text[i];
        const next = text[i+1];

        if (char === '"') {
            if (inQuotes && next === '"') {
                row[row.length - 1] += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (char === ',' && !inQuotes) {
            row.push("");
        } else if ((char === '\r' || char === '\n') && !inQuotes) {
            if (char === '\r' && next === '\n') i++;
            lines.push(row);
            row = [""];
        } else {
            row[row.length - 1] += char;
        }
    }
    if (row.length > 1 || row[0] !== "") {
        lines.push(row);
    }
    return lines;
}

function handleBatchImport(text) {
    const parsedRows = parseCSV(text);
    if (parsedRows.length < 2) {
        showToast('Invalid CSV format. Please download and use the provided template.');
        return;
    }
    
    // Clear current canvas elements
    state.nodes = [];
    state.connections = [];
    
    // If columnNames is empty, initialize default columns
    if (Object.keys(state.columnNames).length === 0) {
        state.columnNames = {
            1: '[ COLUMN 1 ]',
            2: '[ COLUMN 2 ]',
            3: '[ COLUMN 3 ]',
            4: '[ COLUMN 4 ]',
            5: '[ COLUMN 5 ]'
        };
        state.columnTypes = {
            1: 'company',
            2: 'division',
            3: 'hub',
            4: 'position',
            5: 'team'
        };
        syncColumnNames();
    }
    
    const nameToIdMap = {};
    const connectionsToCreate = [];
    let nodeCount = 0;
    
    // Get cleaned active column names for matching
    const colNamesLower = {};
    const colNums = Object.keys(state.columnNames).map(Number).sort((a,b)=>a-b);
    const maxCol = Math.max(...colNums);
    colNums.forEach(c => {
        colNamesLower[c] = cleanColumnName(state.columnNames[c]).toLowerCase();
    });
    
    // Skip header row
    for (let i = 1; i < parsedRows.length; i++) {
        const row = parsedRows[i];
        if (row.length < 2 || !row[1] || !row[1].trim()) continue; // Skip empty rows or rows without name
        
        const colValueRaw = row[0].trim();
        const colValue = colValueRaw.toLowerCase();
        const nodeName = row[1].trim();
        const parentName = row[2] ? row[2].trim() : '';
        
        // Match column placement mapping
        let colNum = 1;
        
        // 1. Check if numerical index
        const parsedColNum = parseInt(colValue);
        if (!isNaN(parsedColNum) && parsedColNum >= 1 && parsedColNum <= maxCol) {
            colNum = parsedColNum;
        } else {
            // 2. Check if matches active column names (checking contains either direction)
            let matched = false;
            // Try to match from highest to lowest index
            for (let c = colNums.length - 1; c >= 0; c--) {
                const cn = colNums[c];
                if (colNamesLower[cn] && (colNamesLower[cn].includes(colValue) || colValue.includes(colNamesLower[cn]))) {
                    colNum = cn;
                    matched = true;
                    break;
                }
            }
            
            if (!matched) {
                // 3. Fallback substrings
                if (colValue.includes('team') || colValue.includes('member') || colValue.includes('group')) colNum = 5;
                else if (colValue.includes('position') || colValue.includes('role') || colValue.includes('title') || colValue.includes('job') || colValue.includes('specialist')) colNum = 4;
                else if (colValue.includes('hub') || colValue.includes('pod') || colValue.includes('office') || colValue.includes('site') || colValue.includes('location')) colNum = colNums[colNums.length - 2] || 3;
                else if (colValue.includes('division') || colValue.includes('department') || colValue.includes('dept') || colValue.includes('unit')) colNum = 2;
                else if (colValue.includes('company') || colValue.includes('corp') || colValue.includes('firm')) colNum = 1;
                else colNum = 1;
            }
        }
        
        const nodeId = `node-import-${Date.now()}-${Math.floor(Math.random() * 10000)}`;
        nameToIdMap[nodeName.toLowerCase()] = nodeId;
        
        // Generate realistic default properties
        const properties = {
            tax: `${nodeName} Compliance Stack`,
            benefits: 'Standard Corporate Perks',
            security: 'System Access Scope',
            clearance: colNum === 1 ? 'Executive' : (colNum === 2 ? 'Manager' : 'Associate')
        };
        
        // Import employee number and job position if specified
        const empNo = row[5] ? row[5].trim() : '';
        const jobPos = row[6] ? row[6].trim() : '';
        if (empNo) properties.employee_number = empNo;
        if (jobPos) properties.job_position = jobPos;
        
        // Ensure columnTypes mapping is populated on import
        if (!state.columnTypes[colNum]) {
            if (colNum >= 5) {
                state.columnTypes[colNum] = (colValue.includes('team') || colValue.includes('member')) ? 'team' : 'position';
            } else {
                state.columnTypes[colNum] = colNum === 1 ? 'company' : (colNum === 2 ? 'division' : (colNum === 3 ? 'hub' : 'position'));
            }
        }
        
        const newNode = {
            id: nodeId,
            name: nodeName,
            column: colNum,
            properties: properties
        };
        
        // Positions have checklist items (SubResponsibilities)
        if (state.columnTypes[colNum] === 'position') {
            const respRaw = row[3] ? row[3].trim() : '';
            if (respRaw) {
                newNode.subitems = respRaw.split(',').map(x => x.trim()).filter(Boolean);
            } else {
                newNode.subitems = ['Core Responsibilities', 'Tasks'];
            }
        }
        
        // Teams have Team Members
        if (state.columnTypes[colNum] === 'team') {
            const teamRaw = row[4] ? row[4].trim() : '';
            if (teamRaw) {
                newNode.teammembers = teamRaw.split(',').map(x => x.trim()).filter(Boolean);
            } else {
                newNode.teammembers = ['Team Lead'];
            }
        }
        
        state.nodes.push(newNode);
        nodeCount++;
        
        // Save parent connection details to map once all nodes are registered in state
        if (parentName) {
            connectionsToCreate.push({ childName: nodeName, parentName: parentName });
        }
    }
    
    // Map connections
    connectionsToCreate.forEach(conn => {
        const fromId = nameToIdMap[conn.parentName.toLowerCase()];
        const toId = nameToIdMap[conn.childName.toLowerCase()];
        
        if (fromId && toId) {
            state.connections.push({ from: fromId, to: toId });
        }
    });
    
    // Refresh GUI states
    state.selectedNodeId = null;
    closeSidebar();
    
    renderCanvas();
    drawConnections();
    showToast(`Successfully imported ${nodeCount} organization elements!`);
}

function setupImportListeners() {
    if (DOM.downloadTemplateBtn) {
        DOM.downloadTemplateBtn.addEventListener('click', () => {
            downloadCSVTemplate();
        });
    }
    
    if (DOM.importBtn) {
        DOM.importBtn.addEventListener('click', () => {
            DOM.importFileInput.click();
        });
    }
    
    const addSoloColBtn = document.getElementById('add-solo-col-btn');
    const addTeamColBtn = document.getElementById('add-team-col-btn');
    
    if (addSoloColBtn) {
        addSoloColBtn.addEventListener('click', () => {
            addColumnOfType('position');
        });
    }
    
    if (addTeamColBtn) {
        addTeamColBtn.addEventListener('click', () => {
            addColumnOfType('team');
        });
    }
    
    if (DOM.clearCanvasBtn) {
        DOM.clearCanvasBtn.addEventListener('click', () => {
            if (confirm('Are you sure you want to clear all nodes and connections from the canvas?')) {
                state.nodes = [];
                state.connections = [];
                state.selectedNodeId = null;
                closeSidebar();
                renderCanvas();
                drawConnections();
                showToast('Canvas cleared successfully!');
            }
        });
    }
    
    // resetCanvasBtn listener removed
    
    if (DOM.importFileInput) {
        DOM.importFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(evt) {
                handleBatchImport(evt.target.result);
                // Reset input so importing the same file again fires event
                DOM.importFileInput.value = '';
            };
            reader.readAsText(file);
        });
    }
}

function addColumnOfType(type) {
    const colNums = Object.keys(state.columnNames).map(Number).sort((a,b)=>a-b);
    const nextColNum = colNums.length > 0 ? Math.max(...colNums) + 1 : 1;
    
    // Add column type and name to state
    state.columnTypes[nextColNum] = type;
    const typeLabel = type === 'team' ? 'TEAM' : 'SOLO';
    state.columnNames[nextColNum] = `[ ${typeLabel} COLUMN ${nextColNum} ]`;
    
    // Sync column names & re-render canvas
    syncColumnNames();
    renderCanvas();
    drawConnections();
    
    // Auto-hide popover display briefly so the mouse leaving triggers standard hover behavior
    const popover = document.getElementById('add-column-popover');
    if (popover) {
        popover.style.display = 'none';
        setTimeout(() => {
            popover.style.display = '';
        }, 100);
    }
    
    showToast(`Added ${type === 'team' ? 'Team' : 'Solo'} Column ${nextColNum}!`);
}

// Team members list renderer
function renderTeamMembersList(node) {
    if (!DOM.teamMembersList) return;
    DOM.teamMembersList.innerHTML = '';
    if (!node.teammembers) node.teammembers = [];
    
    node.teammembers.forEach((member, index) => {
        const li = document.createElement('li');
        li.className = 'prop-pill';
        li.style.width = '100%';
        li.style.display = 'flex';
        li.style.alignItems = 'center';
        li.style.justifyContent = 'space-between';
        li.style.gap = '8px';
        
        li.innerHTML = `
            <input type="text" class="prop-pill-input" value="${member.replace(/"/g, '&quot;')}" style="background: transparent; border: none; color: var(--text-primary); font-family: var(--font-body); font-size: 12px; flex-grow: 1; outline: none; padding: 2px 4px;">
            <button class="prop-pill-delete" data-index="${index}" style="background: transparent; border: none; color: var(--text-muted); cursor: pointer; flex-shrink: 0; transition: color 0.15s;"><i class="fa-solid fa-xmark"></i></button>
        `;
        
        const inputEl = li.querySelector('.prop-pill-input');
        inputEl.addEventListener('input', (e) => {
            node.teammembers[index] = e.target.value;
        });
        
        inputEl.addEventListener('keydown', (e) => {
            e.stopPropagation();
        });
        
        const deleteBtn = li.querySelector('.prop-pill-delete');
        deleteBtn.addEventListener('click', () => {
            node.teammembers.splice(index, 1);
            renderTeamMembersList(node);
            renderCanvas();
        });
        
        deleteBtn.addEventListener('mouseenter', () => {
            deleteBtn.style.color = 'var(--accent-red)';
        });
        deleteBtn.addEventListener('mouseleave', () => {
            deleteBtn.style.color = 'var(--text-muted)';
        });
        
        DOM.teamMembersList.appendChild(li);
    });
}

function setupTeamListeners() {
    if (!DOM.addTeamMemberBtn) return;
    
    DOM.addTeamMemberBtn.addEventListener('click', () => {
        const val = DOM.newTeamMemberInput ? DOM.newTeamMemberInput.value.trim() : '';
        if (!val || !state.selectedNodeId) return;
        
        const node = state.nodes.find(n => n.id === state.selectedNodeId);
        if (!node) return;
        if (!node.teammembers) node.teammembers = [];
        
        node.teammembers.push(val);
        if (DOM.newTeamMemberInput) DOM.newTeamMemberInput.value = '';
        renderTeamMembersList(node);
        renderCanvas();
    });

    if (DOM.newTeamMemberInput) {
        DOM.newTeamMemberInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                DOM.addTeamMemberBtn.click();
            }
        });
    }
}

// Safe utility wrapper for custom floating scrollbars (if needed in UI)
function createFloatingScrollbar(element) {
    if (!element) return;
    element.classList.add('custom-scrollbar');
}

// --------------------------------------------------------------------------
// 10. INITIALIZATION
// --------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    setupColumnTitleListeners();
    setupZoomListeners();
    setupSearchFilterListener();
    setupImportListeners();
    setupTeamListeners();
    syncColumnNames();
    
    // Attach custom floating scrollbars to scrollable areas
    if (DOM.canvasBoard) {
        createFloatingScrollbar(DOM.canvasBoard);
    }
    createFloatingScrollbar(document.querySelector('.dashboard-main'));
    document.querySelectorAll('.sidebar-body').forEach(body => {
        createFloatingScrollbar(body);
    });
    
    navigateToStep(1);
});
