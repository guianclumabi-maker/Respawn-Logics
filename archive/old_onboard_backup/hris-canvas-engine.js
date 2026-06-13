/* ==========================================================================
   HRIS ORG CHART CANVAS ENGINE
   For: Project Respawn Logics — Step 2 Organization Builder
   Exposes: hrisGetEmployees(), hrisGetPositionNodes()
   ========================================================================== */

/* ---- STATE ---- */
const hrisState = {
    employees: [],
    selectedEmployeeId: null,
    collapsedTeams: new Set(),
    zoomScale: 1.0,
    panX: 0,
    panY: 0,
    isPanning: false,
    panStart: { x: 0, y: 0 },
    pendingImportRows: [],
    orgName: 'Organization'
};

/* ---- UTILS ---- */
function hrisGenId() {
    return 'EMP-' + Math.random().toString(36).substr(2, 5).toUpperCase();
}

function hrisIsDescendant(parentId, targetId, visited = new Set()) {
    if (visited.has(parentId)) return false;
    visited.add(parentId);
    const children = hrisState.employees.filter(e => e.immediateSupervisorId === parentId || e.managerSupervisorId === parentId);
    for (const c of children) {
        if (c.employeeId === targetId) return true;
        if (hrisIsDescendant(c.employeeId, targetId, visited)) return true;
    }
    return false;
}

function hrisGetTier(emp) {
    // Tier based on depth in hierarchy: root = 5, each level down = -1
    let depth = 0;
    let cur = emp;
    const visited = new Set();
    while (cur.immediateSupervisorId) {
        if (visited.has(cur.immediateSupervisorId)) break;
        visited.add(cur.immediateSupervisorId);
        const sup = hrisState.employees.find(e => e.employeeId === cur.immediateSupervisorId);
        if (!sup) break;
        cur = sup;
        depth++;
    }
    return Math.max(1.0, 5.0 - depth);
}

/* ---- CYCLE DETECTION ---- */
function hrisWouldCreateCycle(draggedId, targetSupervisorId) {
    if (!draggedId || !targetSupervisorId) return false;
    if (draggedId === targetSupervisorId) return true;
    let currentId = targetSupervisorId;
    while (currentId) {
        if (currentId === draggedId) return true;
        const current = hrisState.employees.find(e => e.employeeId === currentId);
        currentId = current ? current.immediateSupervisorId : null;
    }
    return false;
}

/* ---- NODE DRAG & DROP BINDING ---- */
function hrisBindNodeDragAndDrop(card, emp) {
    card.setAttribute('draggable', 'true');
    card.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('text/plain', emp.employeeId);
        card.classList.add('dragging');
        e.stopPropagation();
    });
    card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
    });
    card.addEventListener('dragover', (e) => {
        e.preventDefault();
    });
    card.addEventListener('dragenter', (e) => {
        card.classList.add('drag-hover');
        e.preventDefault();
    });
    card.addEventListener('dragleave', () => {
        card.classList.remove('drag-hover');
    });
    card.addEventListener('drop', (e) => {
        e.preventDefault();
        card.classList.remove('drag-hover');
        const draggedId = e.dataTransfer.getData('text/plain');
        if (draggedId && draggedId !== emp.employeeId) {
            if (hrisWouldCreateCycle(draggedId, emp.employeeId)) {
                alert("Cannot link employees! This would create a circular reporting reference.");
                return;
            }
            const employee = hrisState.employees.find(x => x.employeeId === draggedId);
            if (employee) {
                const existingTeam = hrisState.employees.find(x => x.immediateSupervisorId === emp.employeeId && x.isTeamNode);
                if (existingTeam && emp.column === 2) {
                    employee.immediateSupervisorId = existingTeam.employeeId;
                    employee.managerSupervisorId = emp.employeeId;
                } else {
                    employee.immediateSupervisorId = emp.employeeId;
                    employee.managerSupervisorId = emp.immediateSupervisorId || null;
                }
                if (emp.department) employee.department = emp.department;
                hrisPopulateDeptFilter();
                hrisRenderCanvas();
                if (typeof showToast === 'function') {
                    showToast(`Linked ${employee.employeeName} under supervisor ${emp.employeeName}`);
                }
            }
        }
    });
}

/* ---- CALCULATE COLUMNS ---- */
function hrisCalculateColumns() {
    // 1. Identify all supervisors
    const supervisors = new Set();
    hrisState.employees.forEach(emp => {
        if (emp.immediateSupervisorId) {
            supervisors.add(emp.immediateSupervisorId);
        }
        if (emp.isSupervisor || emp.isTeamNode) {
            supervisors.add(emp.employeeId);
        }
    });

    // Helper to calculate depth from CEO
    function getDepth(emp) {
        let depth = 0;
        let current = emp;
        const visited = new Set();
        while (current && current.immediateSupervisorId) {
            if (visited.has(current.employeeId)) break;
            visited.add(current.employeeId);
            const parent = hrisState.employees.find(e => e.employeeId === current.immediateSupervisorId);
            if (!parent) break;
            current = parent;
            depth++;
        }
        return depth;
    }

    // 2. Assign columns
    hrisState.employees.forEach(emp => {
        const isRoot = !emp.immediateSupervisorId && !emp.managerSupervisorId && !emp.isTeamNode;
        const isSupervisor = supervisors.has(emp.employeeId);
        const depth = getDepth(emp);

        if (isRoot) {
            emp.column = 1;
        } else if (emp.isTeamNode) {
            emp.column = 3;
        } else if (isSupervisor) {
            // Supervisor
            if (depth === 1) {
                emp.column = 2; // Direct supervisor reports to CEO
            } else {
                emp.column = 3; // Sub-manager or Team Node reports to Col 2 Manager
            }
        } else {
            // Contributor (no reports)
            const supervisor = hrisState.employees.find(e => e.employeeId === emp.immediateSupervisorId);
            if (supervisor && (supervisor.column === 3 || supervisor.isTeamNode)) {
                emp.column = 4;
            } else {
                emp.column = 3;
            }
        }
    });
}

/* ---- DEPT FILTER ---- */
function hrisPopulateDeptFilter() {
    const sel = document.getElementById('hris-dept-filter');
    if (!sel) return;
    const current = sel.value;
    const depts = ['All', ...new Set(hrisState.employees.map(e => e.department).filter(Boolean))];
    sel.innerHTML = depts.map(d => `<option value="${d}">${d === 'All' ? 'All Departments' : d}</option>`).join('');
    sel.value = depts.includes(current) ? current : 'All';
}

/* ---- RENDER CANVAS ---- */
function hrisRenderCanvas() {
    hrisCalculateColumns();

    const viewport = document.getElementById('hris-canvas-viewport');
    viewport?.querySelector('.hris-empty-canvas-placeholder')?.remove();

    const board = document.getElementById('hris-canvas-board');
    if (!board) return;

    const svg = document.getElementById('hris-connections-svg');
    // Remove all nodes (keep SVG)
    [...board.children].forEach(c => { if (c !== svg) c.remove(); });

    const searchQuery = (document.getElementById('hris-search-input')?.value || '').toLowerCase().trim();
    const deptFilter = document.getElementById('hris-dept-filter')?.value || 'All';

    // Pre-group team elements reporting to each supervisor
    const teamGroups = new Map();
    hrisState.employees.forEach(emp => {
        if (emp.column === 4) {
            const supId = emp.immediateSupervisorId || 'orphan';
            if (!teamGroups.has(supId)) teamGroups.set(supId, []);
            teamGroups.get(supId).push(emp);
        }
    });

    // Check if there are any employees in hrisState
    if (hrisState.employees.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'hris-empty-canvas-placeholder';
        empty.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;position:absolute;top:0;left:0;width:100%;height:100%;gap:16px;color:rgba(255,255,255,0.2);z-index:2;pointer-events:none;';
        empty.innerHTML = `
            <i class="fa-solid fa-circle-nodes" style="font-size:48px;"></i>
            <div style="text-align:center;">
                <div style="font-family:'Outfit',sans-serif;font-weight:800;font-size:18px;color:rgba(255,255,255,0.3);margin-bottom:6px;">Canvas is Empty</div>
                <div style="font-size:12px;color:rgba(255,255,255,0.2);">Add employees using the toolbar above or import a CSV roster.</div>
            </div>`;
        viewport?.appendChild(empty);
        hrisDrawConnections();
        return;
    }

    // We render columns 1, 2, 3, and 4
    [1, 2, 3, 4].forEach(colNum => {
        // Calculate visible items in this column to see if we should display it
        let visibleCount = 0;
        if (colNum === 1 || colNum === 2) {
            visibleCount = hrisState.employees.filter(e => e.column === colNum).length;
        } else if (colNum === 3) {
            visibleCount = hrisState.employees.filter(e => e.column === 3).length;
        } else if (colNum === 4) {
            visibleCount = hrisState.employees.filter(e => {
                if (e.column !== 4) return false;
                const supervisor = hrisState.employees.find(sup => sup.employeeId === e.immediateSupervisorId);
                const teamName = supervisor ? `${supervisor.department || 'General'} Team` : 'Orphans Team';
                return !hrisState.collapsedTeams.has(teamName);
            }).length;
        }

        // Hide column 4 if no team node is expanded
        if (colNum === 4 && visibleCount === 0) return;

        const colEl = document.createElement('div');
        colEl.className = 'hris-column-el';
        colEl.setAttribute('data-col', colNum);

        // Column header label
        const colLabel = document.createElement('div');
        colLabel.style.cssText = 'font-size:10px;font-weight:700;color:rgba(255,255,255,0.3);letter-spacing:2px;text-transform:uppercase;text-align:center;margin-bottom:12px;';
        const colLabelMap = {
            1: '[ CEO / ROOT ]',
            2: '[ MANAGERS ]',
            3: '[ TEAMS ]',
            4: '[ TEAM MEMBERS ]'
        };
        colLabel.textContent = colLabelMap[colNum];
        colEl.appendChild(colLabel);

        if (colNum === 1 || colNum === 2) {
            // Render Position Cards
            const soloEmployees = hrisState.employees.filter(e => e.column === colNum);
            soloEmployees.forEach(emp => {
                const isSelected = emp.employeeId === hrisState.selectedEmployeeId;
                const tier = hrisGetTier(emp);
                const matchesSearch = !searchQuery || emp.employeeName.toLowerCase().includes(searchQuery) || emp.position.toLowerCase().includes(searchQuery) || emp.employeeId.toLowerCase().includes(searchQuery);
                const matchesDept = deptFilter === 'All' || emp.department === deptFilter;

                let glowClass = 'glow-purple';
                if (tier >= 4.0) glowClass = 'glow-gold';
                else if (tier <= 2.0) glowClass = 'glow-cyan';

                const card = document.createElement('div');
                card.className = `hris-node-card ${glowClass}${isSelected ? ' selected' : ''}`;
                card.setAttribute('data-emp-id', emp.employeeId);
                card.style.cssText = `
                    border-radius: 14px;
                    padding: 16px;
                    width: 240px;
                    opacity: ${(!matchesSearch || !matchesDept) ? '0.25' : '1'};
                    filter: ${(!matchesSearch || !matchesDept) ? 'grayscale(40%)' : 'none'};
                    position: relative;
                `;

                const gradMap = {
                    'glow-gold': 'linear-gradient(to right, #f59e0b, #f97316)',
                    'glow-purple': 'linear-gradient(to right, #8b5cf6, #ec4899)',
                    'glow-cyan': 'linear-gradient(to right, #06b6d4, #3b82f6)'
                };
                const supervisorName = emp.immediateSupervisorId
                    ? (hrisState.employees.find(s => s.employeeId === emp.immediateSupervisorId)?.employeeName || emp.immediateSupervisorId)
                    : null;

                card.innerHTML = `
                    <div style="position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;background:${gradMap[glowClass]};"></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:4px;">
                            <i class="fa-solid fa-shield" style="color:#8b5cf6;font-size:8px;"></i> Tier ${tier.toFixed(1)}
                        </span>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:9px;padding:2px 8px;border-radius:20px;background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.5);font-weight:600;">
                                ${hrisState.employees.filter(e => e.immediateSupervisorId === emp.employeeId).length > 0 ? 'Supervisor' : 'Staff'}
                            </span>
                            <button type="button" class="hris-delete-card-btn" data-emp-id="${emp.employeeId}" style="background:transparent;border:none;color:#f87171;cursor:pointer;padding:2px;font-size:10px;transition:color 0.15s;outline:none;" title="Delete employee">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div style="font-family:'Outfit',sans-serif;font-weight:700;font-size:15px;color:#fff;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${emp.employeeName}</div>
                    <div style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:10px;display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <i class="fa-solid fa-briefcase" style="color:#ec4899;font-size:9px;"></i>
                        ${emp.position}
                    </div>
                    <div style="border-top:1px solid rgba(255,255,255,0.05);padding-top:8px;display:flex;flex-direction:column;gap:4px;">
                        <div style="font-size:9px;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                            <i class="fa-solid fa-id-card" style="color:#22d3ee;"></i> ID: ${emp.employeeId} 
                            ${emp.employeeNumber && emp.employeeNumber !== emp.employeeId ? `| <span style="color:rgba(255,255,255,0.35);">No.</span> ${emp.employeeNumber}` : ''}
                        </div>
                        ${supervisorName ? `<div style="font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;">
                            <i class="fa-solid fa-user" style="color:#a78bfa;"></i> ${supervisorName}
                        </div>` : ''}
                        <div style="font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;">
                            <i class="fa-solid fa-building" style="color:rgba(255,255,255,0.3);"></i> ${emp.department || 'Unassigned'}
                        </div>
                    </div>`;

                card.addEventListener('click', (e) => {
                    e.stopPropagation();
                    hrisSelectEmployee(emp.employeeId);
                });

                card.querySelector('.hris-delete-card-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    hrisDeleteEmployeeById(emp.employeeId);
                });

                hrisBindNodeDragAndDrop(card, emp);

                colEl.appendChild(card);
            });
        } else if (colNum === 3) {
            const col3Employees = hrisState.employees.filter(e => e.column === 3);
            col3Employees.forEach(emp => {
                const isSelected = emp.employeeId === hrisState.selectedEmployeeId;
                const matchesDept = deptFilter === 'All' || emp.department === deptFilter;

                if (emp.isTeamNode) {
                    const members = hrisState.employees.filter(e => e.immediateSupervisorId === emp.employeeId);
                    const teamName = emp.employeeName;
                    
                    const matchesSearch = !searchQuery || 
                        teamName.toLowerCase().includes(searchQuery) ||
                        members.some(m => m.employeeName.toLowerCase().includes(searchQuery) || m.position.toLowerCase().includes(searchQuery));

                    const isCollapsed = hrisState.collapsedTeams.has(teamName);

                    const card = document.createElement('div');
                    card.className = 'hris-node-card glow-cyan' + (isSelected ? ' selected' : '');
                    card.id = `node-${emp.employeeId}`;
                    card.setAttribute('data-emp-id', emp.employeeId);
                    card.style.cssText = `
                        border-radius: 14px;
                        padding: 16px;
                        width: 240px;
                        border: 1px solid rgba(6, 182, 212, 0.25);
                        background: rgba(22, 24, 33, 0.75);
                        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.37);
                        position: relative;
                        opacity: ${(!matchesSearch || !matchesDept) ? '0.25' : '1'};
                        filter: ${(!matchesSearch || !matchesDept) ? 'grayscale(40%)' : 'none'};
                    `;

                    const lead = hrisState.employees.find(x => x.employeeId === emp.immediateSupervisorId);
                    const leadName = lead ? lead.employeeName : 'Unknown';

                    card.innerHTML = `
                        <div style="position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;background:linear-gradient(to right, #06b6d4, #3b82f6);"></div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;color:#22d3ee;">
                                ${emp.department || 'General'}
                            </span>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="font-size:9px;padding:2px 8px;border-radius:20px;background:rgba(6,182,212,0.1);color:#22d3ee;font-weight:600;">Team Node</span>
                                <button type="button" class="hris-delete-team-btn" data-emp-id="${emp.employeeId}" style="background:transparent;border:none;color:#f87171;cursor:pointer;padding:2px;font-size:10px;transition:color 0.15s;" title="Delete all team members">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div style="font-family:'Outfit',sans-serif;font-weight:700;font-size:15px;color:#fff;margin-bottom:2px;">${teamName}</div>
                        <div style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                            <i class="fa-solid fa-users" style="color:#22d3ee;"></i> ${members.length} Contributors
                        </div>
                        <div style="border-top:1px solid rgba(255,255,255,0.05);padding-top:8px;margin-bottom:10px;display:flex;flex-direction:column;gap:4px;">
                            <div style="font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;">
                                <i class="fa-solid fa-user" style="color:#a78bfa;"></i> Lead: ${leadName}
                            </div>
                            <div style="font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;">
                                <i class="fa-solid fa-gift" style="color:#34d399;"></i> Standard Corporate Perks
                            </div>
                        </div>
                        <div style="border-top:1px solid rgba(255,255,255,0.05);padding-top:8px;">
                            <button type="button" class="hris-team-toggle-btn" style="width:100%;background:transparent;border:none;color:#a78bfa;font-weight:700;font-size:10px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;padding:0;outline:none;">
                                <span>${isCollapsed ? 'EXPAND TEAM VIEW' : 'COLLAPSE TEAM VIEW'}</span>
                                <i class="fa-solid ${isCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'}"></i>
                            </button>
                        </div>
                    `;

                    card.addEventListener('click', (e) => {
                        e.stopPropagation();
                        hrisSelectEmployee(emp.employeeId);
                    });
                    card.querySelector('.hris-delete-team-btn').addEventListener('click', (e) => {
                        e.stopPropagation();
                        hrisDeleteTeam(emp.employeeId);
                    });
                    card.querySelector('.hris-team-toggle-btn').addEventListener('click', (e) => {
                        e.stopPropagation();
                        hrisToggleTeamCollapse(teamName);
                    });

                    card.addEventListener('dragover', (e) => { e.preventDefault(); });
                    card.addEventListener('dragenter', (e) => { card.classList.add('drag-hover'); e.preventDefault(); });
                    card.addEventListener('dragleave', () => { card.classList.remove('drag-hover'); });
                    card.addEventListener('drop', (e) => {
                        e.preventDefault();
                        card.classList.remove('drag-hover');
                        const draggedId = e.dataTransfer.getData('text/plain');
                        if (draggedId && draggedId !== emp.employeeId) {
                            if (hrisWouldCreateCycle(draggedId, emp.employeeId)) {
                                alert("Cannot link employees! This would create a circular reporting reference.");
                                return;
                            }
                            const employee = hrisState.employees.find(x => x.employeeId === draggedId);
                            if (employee) {
                                employee.immediateSupervisorId = emp.employeeId;
                                employee.managerSupervisorId = emp.immediateSupervisorId || null;
                                if (emp.department) employee.department = emp.department;
                                hrisPopulateDeptFilter();
                                hrisRenderCanvas();
                                if (typeof showToast === 'function') {
                                    showToast(`Moved ${employee.employeeName} to ${teamName}`);
                                }
                            }
                        }
                    });

                    colEl.appendChild(card);
                } else {
                    // Solo Contributor Position Card
                    const tier = hrisGetTier(emp);
                    const matchesSearch = !searchQuery || emp.employeeName.toLowerCase().includes(searchQuery) || emp.position.toLowerCase().includes(searchQuery) || emp.employeeId.toLowerCase().includes(searchQuery);

                    let glowClass = 'glow-purple';
                    if (tier >= 4.0) glowClass = 'glow-gold';
                    else if (tier <= 2.0) glowClass = 'glow-cyan';

                    const card = document.createElement('div');
                    card.className = `hris-node-card ${glowClass}${isSelected ? ' selected' : ''}`;
                    card.setAttribute('data-emp-id', emp.employeeId);
                    card.style.cssText = `
                        border-radius: 14px;
                        padding: 16px;
                        width: 240px;
                        opacity: ${(!matchesSearch || !matchesDept) ? '0.25' : '1'};
                        filter: ${(!matchesSearch || !matchesDept) ? 'grayscale(40%)' : 'none'};
                        position: relative;
                    `;

                    const gradMap = {
                        'glow-gold': 'linear-gradient(to right, #f59e0b, #f97316)',
                        'glow-purple': 'linear-gradient(to right, #8b5cf6, #ec4899)',
                        'glow-cyan': 'linear-gradient(to right, #06b6d4, #3b82f6)'
                    };
                    const supervisorName = emp.immediateSupervisorId
                        ? (hrisState.employees.find(s => s.employeeId === emp.immediateSupervisorId)?.employeeName || emp.immediateSupervisorId)
                        : null;

                    card.innerHTML = `
                        <div style="position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;background:${gradMap[glowClass]};"></div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:4px;">
                                <i class="fa-solid fa-shield" style="color:#8b5cf6;font-size:8px;"></i> Tier ${tier.toFixed(1)}
                            </span>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="font-size:9px;padding:2px 8px;border-radius:20px;background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.5);font-weight:600;">Staff</span>
                                <button type="button" class="hris-delete-card-btn" data-emp-id="${emp.employeeId}" style="background:transparent;border:none;color:#f87171;cursor:pointer;padding:2px;font-size:10px;transition:color 0.15s;outline:none;" title="Delete employee">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div style="font-family:'Outfit',sans-serif;font-weight:700;font-size:15px;color:#fff;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${emp.employeeName}</div>
                        <div style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:10px;display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <i class="fa-solid fa-briefcase" style="color:#ec4899;font-size:9px;"></i>
                            ${emp.position}
                        </div>
                        <div style="border-top:1px solid rgba(255,255,255,0.05);padding-top:8px;display:flex;flex-direction:column;gap:4px;">
                            <div style="font-size:9px;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                                <i class="fa-solid fa-id-card" style="color:#22d3ee;"></i> ID: ${emp.employeeId} 
                                ${emp.employeeNumber && emp.employeeNumber !== emp.employeeId ? `| <span style="color:rgba(255,255,255,0.35);">No.</span> ${emp.employeeNumber}` : ''}
                            </div>
                            ${supervisorName ? `<div style="font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;">
                                <i class="fa-solid fa-user" style="color:#a78bfa;"></i> ${supervisorName}
                            </div>` : ''}
                            <div style="font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;">
                                <i class="fa-solid fa-building" style="color:rgba(255,255,255,0.3);"></i> ${emp.department || 'Unassigned'}
                            </div>
                        </div>`;

                    card.addEventListener('click', (e) => {
                        e.stopPropagation();
                        hrisSelectEmployee(emp.employeeId);
                    });
                    card.querySelector('.hris-delete-card-btn').addEventListener('click', (e) => {
                        e.stopPropagation();
                        hrisDeleteEmployeeById(emp.employeeId);
                    });

                    hrisBindNodeDragAndDrop(card, emp);
                    colEl.appendChild(card);
                }
        } else if (colNum === 4) {
            // Render expanded team members
            const contributorEmployees = hrisState.employees.filter(e => {
                if (e.column !== 4) return false;
                const supervisor = hrisState.employees.find(sup => sup.employeeId === e.immediateSupervisorId);
                const teamName = supervisor ? `${supervisor.department || 'General'} Team` : 'Orphans Team';
                return !hrisState.collapsedTeams.has(teamName);
            });

            contributorEmployees.forEach(emp => {
                const isSelected = emp.employeeId === hrisState.selectedEmployeeId;
                const tier = hrisGetTier(emp);
                const matchesSearch = !searchQuery || emp.employeeName.toLowerCase().includes(searchQuery) || emp.position.toLowerCase().includes(searchQuery) || emp.employeeId.toLowerCase().includes(searchQuery);
                const matchesDept = deptFilter === 'All' || emp.department === deptFilter;

                const card = document.createElement('div');
                card.className = `hris-node-card glow-cyan${isSelected ? ' selected' : ''}`;
                card.setAttribute('data-emp-id', emp.employeeId);
                card.style.cssText = `
                    border-radius: 14px;
                    padding: 16px;
                    width: 240px;
                    opacity: ${(!matchesSearch || !matchesDept) ? '0.25' : '1'};
                    filter: ${(!matchesSearch || !matchesDept) ? 'grayscale(40%)' : 'none'};
                    position: relative;
                `;

                card.innerHTML = `
                    <div style="position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;background:linear-gradient(to right, #06b6d4, #3b82f6);"></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:4px;">
                            <i class="fa-solid fa-shield" style="color:#06b6d4;font-size:8px;"></i> Tier ${tier.toFixed(1)}
                        </span>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:9px;padding:2px 8px;border-radius:20px;background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.5);font-weight:600;">Staff</span>
                            <button type="button" class="hris-delete-card-btn" data-emp-id="${emp.employeeId}" style="background:transparent;border:none;color:#f87171;cursor:pointer;padding:2px;font-size:10px;transition:color 0.15s;outline:none;" title="Delete employee">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div style="font-family:'Outfit',sans-serif;font-weight:700;font-size:15px;color:#fff;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${emp.employeeName}</div>
                    <div style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:10px;display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <i class="fa-solid fa-briefcase" style="color:#ec4899;font-size:9px;"></i>
                        ${emp.position}
                    </div>
                    <div style="border-top:1px solid rgba(255,255,255,0.05);padding-top:8px;display:flex;flex-direction:column;gap:4px;">
                        <div style="font-size:9px;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                            <i class="fa-solid fa-id-card" style="color:#22d3ee;"></i> ID: ${emp.employeeId}
                            ${emp.employeeNumber && emp.employeeNumber !== emp.employeeId ? `| <span style="color:rgba(255,255,255,0.35);">No.</span> ${emp.employeeNumber}` : ''}
                        </div>
                        <div style="font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;">
                            <i class="fa-solid fa-user-tie" style="color:#a78bfa;"></i> Team Lead ID: ${emp.immediateSupervisorId}
                        </div>
                        <div style="font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;">
                            <i class="fa-solid fa-building" style="color:rgba(255,255,255,0.3);"></i> ${emp.department || 'Unassigned'}
                        </div>
                    </div>`;

                card.addEventListener('click', (e) => {
                    e.stopPropagation();
                    hrisSelectEmployee(emp.employeeId);
                });

                card.querySelector('.hris-delete-card-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    hrisDeleteEmployeeById(emp.employeeId);
                });

                hrisBindNodeDragAndDrop(card, emp);

                colEl.appendChild(card);
            });
        }

        board.appendChild(colEl);
    });

    // Apply transform
    board.style.transform = `scale(${hrisState.zoomScale}) translate(${hrisState.panX}px, ${hrisState.panY}px)`;

    // Draw connectors after DOM is ready
    requestAnimationFrame(hrisDrawConnections);
}

/* ---- DRAW SVG CONNECTIONS ---- */
function hrisDrawConnections() {
    const svg = document.getElementById('hris-connections-svg');
    const board = document.getElementById('hris-canvas-board');
    if (!svg || !board) return;

    const boardRect = board.getBoundingClientRect();
    svg.setAttribute('viewBox', `0 0 ${boardRect.width / hrisState.zoomScale} ${boardRect.height / hrisState.zoomScale}`);
    svg.innerHTML = '';

    const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    ['purple', 'gold', 'cyan'].forEach(color => {
        const g = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
        g.setAttribute('id', `hris-grad-${color}`);
        g.setAttribute('x1', '0%'); g.setAttribute('y1', '0%');
        g.setAttribute('x2', '100%'); g.setAttribute('y2', '0%');
        const colorMap = {
            purple: ['#8b5cf6', '#ec4899'],
            gold: ['#f59e0b', '#f97316'],
            cyan: ['#06b6d4', '#3b82f6']
        };
        colorMap[color].forEach((c, i) => {
            const stop = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
            stop.setAttribute('offset', i === 0 ? '0%' : '100%');
            stop.setAttribute('stop-color', c);
            g.appendChild(stop);
        });
        defs.appendChild(g);
    });
    svg.appendChild(defs);

    const scale = hrisState.zoomScale;
    const bLeft = boardRect.left;
    const bTop = boardRect.top;

    function drawLine(fromEl, toEl, color, isDashed) {
        if (!fromEl || !toEl) return;
        const fromRect = fromEl.getBoundingClientRect();
        const toRect = toEl.getBoundingClientRect();

        const x1 = (fromRect.right - bLeft) / scale;
        const y1 = (fromRect.top + fromRect.height / 2 - bTop) / scale;
        const x2 = (toRect.left - bLeft) / scale;
        const y2 = (toRect.top + toRect.height / 2 - bTop) / scale;

        const cx = (x1 + x2) / 2;

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', `M ${x1} ${y1} C ${cx} ${y1}, ${cx} ${y2}, ${x2} ${y2}`);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', color);
        path.setAttribute('stroke-width', '1.5');
        path.setAttribute('stroke-opacity', '0.6');
        if (isDashed) {
            path.setAttribute('stroke-dasharray', '4,4');
        }
        svg.appendChild(path);

        // Visual anchor circles
        const c1 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        c1.setAttribute('cx', x1); c1.setAttribute('cy', y1); c1.setAttribute('r', '2.5'); c1.setAttribute('fill', color);
        svg.appendChild(c1);

        const c2 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        c2.setAttribute('cx', x2); c2.setAttribute('cy', y2); c2.setAttribute('r', '2.5'); c2.setAttribute('fill', color);
        svg.appendChild(c2);
    }

    // Connect nodes
    hrisState.employees.forEach(emp => {
        if (!emp.immediateSupervisorId) return;

        const childEl = board.querySelector(`[data-emp-id="${emp.employeeId}"]`);
        const parentEl = board.querySelector(`[data-emp-id="${emp.immediateSupervisorId}"]`);
        if (!childEl || !parentEl) return;

        if (emp.column === 2) {
            drawLine(parentEl, childEl, 'url(#hris-grad-purple)', false);
        } else if (emp.isTeamNode) {
            drawLine(parentEl, childEl, 'url(#hris-grad-cyan)', true); // dashed cyan line for Team Node
        } else if (emp.column === 3) {
            drawLine(parentEl, childEl, 'url(#hris-grad-cyan)', false); // Solid cyan line for Column 3 solo contributor
        } else if (emp.column === 4) {
            drawLine(parentEl, childEl, 'url(#hris-grad-cyan)', true); // dashed cyan line for team contributors in Column 4
        }
    });
}

/* ---- TEAM MANIPULATIONS ---- */
function hrisToggleTeamCollapse(teamName) {
    if (hrisState.collapsedTeams.has(teamName)) {
        hrisState.collapsedTeams.delete(teamName);
    } else {
        hrisState.collapsedTeams.add(teamName);
    }
    hrisRenderCanvas();
}

function hrisDeleteTeam(teamNodeId) {
    if (!confirm('Are you sure you want to delete this team and all its members?')) return;
    
    // Remove the team node and all its direct reportees
    hrisState.employees = hrisState.employees.filter(e => 
        e.employeeId !== teamNodeId && e.immediateSupervisorId !== teamNodeId
    );

    // Close sidebar if selected employee was deleted
    if (hrisState.selectedEmployeeId) {
        const stillExists = hrisState.employees.some(e => e.employeeId === hrisState.selectedEmployeeId);
        if (!stillExists) {
            hrisCloseSidebar();
        }
    }

    hrisPopulateDeptFilter();
    hrisRenderCanvas();
    if (typeof showToast === 'function') showToast('Deleted team and its members.');
}

/* ---- SIDEBAR LOGIC ---- */
function hrisSelectEmployee(empId) {
    hrisState.selectedEmployeeId = empId;
    hrisRenderCanvas();

    if (typeof empId === 'string' && empId.startsWith('team-')) {
        document.getElementById('hris-sidebar')?.classList.remove('visible');
        return;
    }

    const emp = hrisState.employees.find(e => e.employeeId === empId);
    if (!emp) return;

    const sidebar = document.getElementById('hris-sidebar');
    sidebar.classList.add('visible');

    document.getElementById('hris-edit-emp-id').value = emp.employeeId;
    document.getElementById('hris-edit-emp-number').value = emp.employeeNumber || emp.employeeId;
    document.getElementById('hris-edit-name').value = emp.employeeName;
    document.getElementById('hris-edit-position').value = emp.position;
    document.getElementById('hris-edit-department').value = emp.department;

    const supSel = document.getElementById('hris-edit-supervisor');
    supSel.innerHTML = '<option value="">No Supervisor (Root / CEO)</option>';
    const mgrSel = document.getElementById('hris-edit-manager-supervisor');
    if (mgrSel) mgrSel.innerHTML = '<option value="">No Manager Supervisor</option>';

    hrisState.employees.forEach(s => {
        if (s.employeeId !== empId && !hrisIsDescendant(s.employeeId, empId)) {
            const opt = document.createElement('option');
            opt.value = s.employeeId;
            opt.text = `${s.employeeName} (${s.position}) [${s.employeeId}]`;
            supSel.appendChild(opt);
        }
        if (mgrSel && s.employeeId !== empId) {
            const opt = document.createElement('option');
            opt.value = s.employeeId;
            opt.text = `${s.employeeName} (${s.position}) [${s.employeeId}]`;
            mgrSel.appendChild(opt);
        }
    });
    supSel.value = emp.immediateSupervisorId || '';
    if (mgrSel) mgrSel.value = emp.managerSupervisorId || '';

    hrisRefreshSupBadge(emp.immediateSupervisorId);
}

function hrisCloseSidebar() {
    hrisState.selectedEmployeeId = null;
    const sidebar = document.getElementById('hris-sidebar');
    sidebar.classList.remove('visible');
    hrisRenderCanvas();
}

function hrisOnSupervisorChange() {
    const val = document.getElementById('hris-edit-supervisor').value || null;
    hrisRefreshSupBadge(val);

    const mgrSel = document.getElementById('hris-edit-manager-supervisor');
    if (mgrSel && val) {
        const sup = hrisState.employees.find(e => e.employeeId === val);
        if (sup) {
            mgrSel.value = sup.immediateSupervisorId || '';
        }
    }
}

function hrisRefreshSupBadge(supervisorId) {
    const badge = document.getElementById('hris-sup-id-badge');
    const val = document.getElementById('hris-sup-id-val');
    const warning = document.getElementById('hris-sup-warning');
    if (supervisorId) {
        val.textContent = supervisorId;
        badge.style.display = 'block';
        warning.style.display = 'none';
    } else {
        badge.style.display = 'none';
        warning.style.display = 'flex';
    }
}

function hrisSaveEmployee() {
    const emp = hrisState.employees.find(e => e.employeeId === hrisState.selectedEmployeeId);
    if (!emp) return;
    emp.employeeName = document.getElementById('hris-edit-name').value.trim() || emp.employeeName;
    emp.employeeNumber = document.getElementById('hris-edit-emp-number').value.trim() || emp.employeeNumber || emp.employeeId;
    emp.position = document.getElementById('hris-edit-position').value.trim() || emp.position;
    emp.department = document.getElementById('hris-edit-department').value.trim() || emp.department;
    emp.immediateSupervisorId = document.getElementById('hris-edit-supervisor').value || null;
    const mgrSel = document.getElementById('hris-edit-manager-supervisor');
    emp.managerSupervisorId = mgrSel ? (mgrSel.value || null) : null;
    hrisPopulateDeptFilter();
    hrisRenderCanvas();
}

function hrisDeleteEmployeeById(empId) {
    if (!empId || !confirm('Remove this employee from the canvas?')) return;
    hrisState.employees = hrisState.employees.filter(e => e.employeeId !== empId);
    // Orphan direct reports
    hrisState.employees.forEach(e => { 
        if (e.immediateSupervisorId === empId) e.immediateSupervisorId = null; 
        if (e.managerSupervisorId === empId) e.managerSupervisorId = null;
    });
    if (hrisState.selectedEmployeeId === empId) {
        hrisCloseSidebar();
    }
    hrisPopulateDeptFilter();
    hrisRenderCanvas();
}

function hrisDeleteEmployee() {
    hrisDeleteEmployeeById(hrisState.selectedEmployeeId);
}

function hrisAddNewEmployee() {
    const selectedId = hrisState.selectedEmployeeId;
    let defaultSupId = null;
    let defaultDept = 'General';

    if (selectedId) {
        const emp = hrisState.employees.find(e => e.employeeId === selectedId);
        if (emp) {
            if (emp.isTeamNode) {
                defaultSupId = emp.employeeId;
            } else if (emp.column === 2) {
                const existingTeam = hrisState.employees.find(x => x.immediateSupervisorId === emp.employeeId && x.isTeamNode);
                if (existingTeam) {
                    defaultSupId = existingTeam.employeeId;
                } else {
                    defaultSupId = emp.employeeId;
                }
            } else {
                defaultSupId = emp.employeeId;
            }
        } else {
            if (typeof selectedId === 'string' && selectedId.startsWith('team-')) {
                defaultSupId = selectedId.substring(5);
            } else {
                defaultSupId = selectedId;
            }
        }
    } else {
        // Fallback to the first CEO/root node if it exists
        const root = hrisState.employees.find(e => !e.immediateSupervisorId);
        if (root) {
            defaultSupId = root.employeeId;
        }
    }

    if (defaultSupId) {
        const sup = hrisState.employees.find(e => e.employeeId === defaultSupId);
        if (sup && sup.department) {
            defaultDept = sup.department;
        }
    }

    hrisCreateContextualEmployee(defaultSupId, false, defaultDept);
}

function hrisCreateContextualEmployee(supId, isTeamMember, dept, isSupervisor = false) {
    const id = hrisGenId();
    let mgrSupId = null;
    if (supId) {
        const sup = hrisState.employees.find(e => e.employeeId === supId);
        if (sup) {
            mgrSupId = sup.immediateSupervisorId || null;
        }
    }

    hrisState.employees.push({
        employeeId: id,
        employeeName: 'New Employee',
        position: 'Position Title',
        department: dept || 'General',
        immediateSupervisorId: supId || null,
        employeeNumber: id,
        managerSupervisorId: mgrSupId,
        isSupervisor: isSupervisor
    });
    hrisPopulateDeptFilter();
    hrisRenderCanvas();
    // Auto-select the new employee
    setTimeout(() => hrisSelectEmployee(id), 50);
}

function hrisSetupAddEmployeeDropdown() {
    const container = document.getElementById('hris-add-emp-menu-container');
    const dropdown = document.getElementById('hris-add-emp-dropdown');
    const btn = document.getElementById('hris-add-employee-btn');
    if (!container || !dropdown || !btn) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isVisible = dropdown.style.display === 'flex';

        if (isVisible) {
            dropdown.style.display = 'none';
            return;
        }

        dropdown.innerHTML = '';
        const selectedId = hrisState.selectedEmployeeId;

        // Determine enabled/disabled states based on selection
        let enableSolo = true;
        let enableTeam = true;

        if (selectedId) {
            const emp = hrisState.employees.find(e => e.employeeId === selectedId);
            if (emp && (emp.column === 3 || emp.column === 4 || emp.isTeamNode)) {
                enableTeam = false; // Cannot create a team group under Column 3 or 4 cards or existing team node
            }
        } else {
            // No selection: Team Group requires selecting a supervisor/manager first
            enableTeam = false;
        }

        // 1. Solo Employee Option
        const opt1 = document.createElement('div');
        opt1.className = 'hris-dropdown-item';
        opt1.innerHTML = '<i class="fa-solid fa-user" style="color:#a78bfa;"></i> Solo Employee';
        if (!enableSolo) {
            opt1.style.opacity = '0.4';
            opt1.style.pointerEvents = 'none';
            opt1.style.cursor = 'not-allowed';
        } else {
            opt1.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.style.display = 'none';

                if (!selectedId) {
                    // No selection: create root node
                    hrisCreateContextualEmployee(null, false, 'General', false);
                } else {
                    const emp = hrisState.employees.find(e => e.employeeId === selectedId);
                    if (emp) {
                        if (emp.isTeamNode) {
                            // Team node selected: add contributor to team
                            hrisCreateContextualEmployee(emp.employeeId, false, emp.department || 'General', false);
                        } else if (emp.column === 1) {
                            // CEO selected: add direct manager (Col 2 supervisor)
                            hrisCreateContextualEmployee(emp.employeeId, false, emp.department || 'General', true);
                        } else if (emp.column === 2) {
                            // Manager selected: check if they already have a Team Node in Column 3
                            const existingTeam = hrisState.employees.find(x => x.immediateSupervisorId === emp.employeeId && x.isTeamNode);
                            if (existingTeam) {
                                // Add contributor under that Team Node
                                hrisCreateContextualEmployee(existingTeam.employeeId, false, emp.department || 'General', false);
                            } else {
                                // Add a direct report in next column (goes to Column 3 as sub-manager card)
                                hrisCreateContextualEmployee(emp.employeeId, false, emp.department || 'General', true);
                            }
                        } else if (emp.column === 3 && emp.isSupervisor) {
                            // Sub-manager selected: add direct report under them (goes to Column 4)
                            hrisCreateContextualEmployee(emp.employeeId, false, emp.department || 'General', false);
                        } else {
                            // Contributor selected: add peer contributor
                            hrisCreateContextualEmployee(emp.immediateSupervisorId || null, false, emp.department || 'General', false);
                        }
                    }
                }
            });
        }

        // 2. Team Group Option
        const opt2 = document.createElement('div');
        opt2.className = 'hris-dropdown-item';
        opt2.innerHTML = '<i class="fa-solid fa-users" style="color:#22d3ee;"></i> Team Group';
        if (!enableTeam) {
            opt2.style.opacity = '0.4';
            opt2.style.pointerEvents = 'none';
            opt2.style.cursor = 'not-allowed';
        } else {
            opt2.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.style.display = 'none';

                let supId = null;
                let dept = 'General';

                if (selectedId) {
                    const emp = hrisState.employees.find(x => x.employeeId === selectedId);
                    if (emp) {
                        if (emp.column === 3 || emp.column === 4 || emp.isTeamNode) {
                            alert('Team Groups can only be created under Leadership (Col 1) or Managers (Col 2).');
                            return;
                        }
                        supId = emp.employeeId;
                        dept = emp.department || 'General';
                    }
                }

                // Create the new Team Node directly reporting to supId
                const id = 'team-node-' + hrisGenId();
                let mgrSupId = null;
                if (supId) {
                    const sup = hrisState.employees.find(x => x.employeeId === supId);
                    if (sup) {
                        mgrSupId = sup.immediateSupervisorId || null;
                    }
                }

                const newTeamNode = {
                    employeeId: id,
                    employeeName: `${dept} Team`,
                    position: 'Team Node',
                    department: dept,
                    immediateSupervisorId: supId,
                    employeeNumber: id,
                    managerSupervisorId: mgrSupId,
                    isSupervisor: false,
                    isTeamNode: true,
                    column: 3
                };

                hrisState.employees.push(newTeamNode);
                hrisPopulateDeptFilter();
                hrisRenderCanvas();
                
                // Select the new team node
                setTimeout(() => hrisSelectEmployee(id), 50);
            });
        }


        dropdown.appendChild(opt1);
        dropdown.appendChild(opt2);
        dropdown.style.display = 'flex';
    });

    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

/* ---- ZOOM & PAN ---- */
function hrisSetZoom(scale) {
    hrisState.zoomScale = Math.max(0.2, Math.min(2.5, scale));
    const label = document.getElementById('hris-zoom-label');
    if (label) label.textContent = Math.round(hrisState.zoomScale * 100) + '%';
    hrisRenderCanvas();
}

function hrisInitPan() {
    const viewport = document.getElementById('hris-canvas-viewport');
    if (!viewport) return;

    viewport.addEventListener('mousedown', (e) => {
        if (e.target.closest('.hris-node-card') || e.target.closest('#hris-sidebar')) return;
        hrisState.isPanning = true;
        hrisState.panStart = { x: e.clientX - hrisState.panX * hrisState.zoomScale, y: e.clientY - hrisState.panY * hrisState.zoomScale };
        viewport.style.cursor = 'grabbing';
    });

    document.addEventListener('mousemove', (e) => {
        if (!hrisState.isPanning) return;
        hrisState.panX = (e.clientX - hrisState.panStart.x) / hrisState.zoomScale;
        hrisState.panY = (e.clientY - hrisState.panStart.y) / hrisState.zoomScale;
        const board = document.getElementById('hris-canvas-board');
        if (board) board.style.transform = `scale(${hrisState.zoomScale}) translate(${hrisState.panX}px, ${hrisState.panY}px)`;
        requestAnimationFrame(hrisDrawConnections);
    });

    document.addEventListener('mouseup', () => {
        hrisState.isPanning = false;
        if (viewport) viewport.style.cursor = 'grab';
    });

    viewport.addEventListener('wheel', (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        hrisSetZoom(hrisState.zoomScale + delta);
    }, { passive: false });

    // Drag and drop support on viewport background to promote a node to CEO/root level
    viewport.addEventListener('dragover', (e) => {
        e.preventDefault();
    });
    viewport.addEventListener('drop', (e) => {
        e.preventDefault();
        const draggedId = e.dataTransfer.getData('text/plain');
        if (draggedId) {
            if (!e.target.closest('.hris-node-card')) {
                const employee = hrisState.employees.find(x => x.employeeId === draggedId);
                if (employee) {
                    if (employee.immediateSupervisorId !== null) {
                        employee.immediateSupervisorId = null;
                        hrisPopulateDeptFilter();
                        hrisRenderCanvas();
                        if (typeof showToast === 'function') {
                            showToast(`Promoted ${employee.employeeName} to CEO / Root Node`);
                        }
                    }
                }
            }
        }
    });
}

/* ---- CSV TEMPLATE DOWNLOAD ---- */
function hrisTriggerDownloadTemplate() {
    const header = 'employee_id,employee_name,position,department,employee_number,immediate_supervisor_id,Manager_supervisor_id';
    const demo = [
        'EMP-001,Richard Branson,Founder & CEO,Executive,EMP-10001,,',
        'EMP-002,Maria Santos,VP of Engineering,Technology,EMP-10002,EMP-001,',
        'EMP-003,Jose Reyes,VP of Finance,Finance,EMP-10003,EMP-001,',
        'EMP-004,Ana Gomez,Software Engineer,Technology,EMP-10004,EMP-002,EMP-001',
        'EMP-005,Pedro Luna,Financial Analyst,Finance,EMP-10005,EMP-003,EMP-001'
    ];
    const csv = [header, ...demo].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'hris_org_template.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
}

/* ---- CSV EXPORT ---- */
function hrisExportCSV() {
    const header = 'employee_id,employee_name,position,department,employee_number,immediate_supervisor_id,Manager_supervisor_id';
    const rows = hrisState.employees.map(e =>
        `"${e.employeeId}","${e.employeeName}","${e.position}","${e.department}","${e.employeeNumber || e.employeeId}","${e.immediateSupervisorId || ''}","${e.managerSupervisorId || ''}"`
    );
    const csv = [header, ...rows].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'org_roster.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
}

/* ---- IMPORT MODAL ---- */
function hrisOpenImportModal() {
    const modal = document.getElementById('hris-import-modal');
    if (modal) { modal.style.display = 'flex'; hrisResetImportModal(); }
}

function hrisCloseImportModal() {
    const modal = document.getElementById('hris-import-modal');
    if (modal) modal.style.display = 'none';
}

function hrisResetImportModal() {
    document.getElementById('hris-upload-zone').style.display = 'block';
    document.getElementById('hris-report-zone').style.display = 'none';
    const input = document.getElementById('hris-csv-input');
    if (input) input.value = '';
    hrisState.pendingImportRows = [];
}

function hrisHandleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target.result;
        hrisParseImportCSV(text, file.name);
    };
    reader.readAsText(file);
}

function hrisSplitCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            result.push(current.replace(/^"|"$/g, '').replace(/""/g, '"').trim());
            current = '';
        } else {
            current += char;
        }
    }
    result.push(current.replace(/^"|"$/g, '').replace(/""/g, '"').trim());
    return result;
}

function hrisParseImportCSV(text, filename) {
    const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
    if (lines.length < 2) { alert('CSV must have a header and at least one data row.'); return; }

    const headers = lines[0].split(',').map(h => h.replace(/"/g, '').trim().toLowerCase().replace(/ /g, '_'));
    const requiredCols = ['employee_id', 'employee_name', 'position', 'department'];
    const missing = requiredCols.filter(c => !headers.includes(c));
    if (missing.length > 0) { alert(`Missing columns: ${missing.join(', ')}`); return; }

    const rows = [];
    const errors = [];
    for (let i = 1; i < lines.length; i++) {
        const vals = hrisSplitCSVLine(lines[i]);
        if (vals.every(v => !v.trim())) {
            continue; // Skip entirely blank CSV rows silently
        }
        const row = {};
        headers.forEach((h, idx) => { row[h] = vals[idx] || ''; });
        if (!row.employee_id || !row.employee_name) {
            errors.push(`Row ${i + 1}: Missing employee_id or employee_name`);
            continue;
        }
        rows.push({
            employeeId: row.employee_id,
            employeeName: row.employee_name,
            position: row.position || 'Unassigned',
            department: row.department || 'General',
            immediateSupervisorId: row.immediate_supervisor_id || null,
            employeeNumber: row.employee_number || row.employee_id,
            managerSupervisorId: row.manager_supervisor_id || null
        });
    }

    // Post-process to resolve supervisor/manager links (from employee_number or employee_id to unique employeeId)
    rows.forEach(r => {
        const cleanSup = (r.immediateSupervisorId || '').trim();
        if (cleanSup && cleanSup.toLowerCase() !== 'n/a') {
            const sup = rows.find(e => e.employeeId === cleanSup || e.employeeNumber === cleanSup);
            r.immediateSupervisorId = sup ? sup.employeeId : cleanSup;
        } else {
            r.immediateSupervisorId = null;
        }

        const cleanMgr = (r.managerSupervisorId || '').trim();
        if (cleanMgr && cleanMgr.toLowerCase() !== 'n/a') {
            const mgr = rows.find(e => e.employeeId === cleanMgr || e.employeeNumber === cleanMgr);
            r.managerSupervisorId = mgr ? mgr.employeeId : cleanMgr;
        } else {
            r.managerSupervisorId = null;
        }
    });

    hrisState.pendingImportRows = rows;

    // Show report
    document.getElementById('hris-upload-zone').style.display = 'none';
    document.getElementById('hris-report-zone').style.display = 'flex';
    document.getElementById('hris-import-filename').innerHTML = `<i class="fa-solid fa-file-csv" style="color:#22d3ee;margin-right:6px;"></i>${filename}`;

    const details = document.getElementById('hris-report-details');
    details.innerHTML = '';

    // Summary badge
    const summary = document.createElement('div');
    summary.style.cssText = 'padding:12px;border-radius:8px;background:rgba(139,92,246,0.05);border:1px solid rgba(139,92,246,0.1);';
    summary.innerHTML = `
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="text-align:center;"><div style="font-size:20px;font-weight:800;color:#a78bfa;">${rows.length}</div><div style="font-size:9px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Employees</div></div>
            <div style="text-align:center;"><div style="font-size:20px;font-weight:800;color:#22d3ee;">${[...new Set(rows.map(r => r.department))].length}</div><div style="font-size:9px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Departments</div></div>
            <div style="text-align:center;"><div style="font-size:20px;font-weight:800;color:#f472b6;">${rows.filter(r => !r.immediateSupervisorId).length}</div><div style="font-size:9px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Root Nodes</div></div>
            ${errors.length > 0 ? `<div style="text-align:center;"><div style="font-size:20px;font-weight:800;color:#f87171;">${errors.length}</div><div style="font-size:9px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Warnings</div></div>` : ''}
        </div>`;
    details.appendChild(summary);

    if (errors.length > 0) {
        const errDiv = document.createElement('div');
        errDiv.style.cssText = 'padding:10px;border-radius:8px;background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.1);';
        errDiv.innerHTML = `<div style="font-size:10px;font-weight:700;color:#f87171;margin-bottom:6px;text-transform:uppercase;letter-spacing:1px;">⚠ Skipped Rows</div>` +
            errors.map(e => `<div style="font-size:10px;color:rgba(255,255,255,0.5);padding:2px 0;">${e}</div>`).join('');
        details.appendChild(errDiv);
    }

    // Preview table
    const previewDiv = document.createElement('div');
    previewDiv.style.cssText = 'border-radius:8px;overflow:hidden;border:1px solid rgba(255,255,255,0.05);';
    const tableHTML = `<table style="width:100%;border-collapse:collapse;font-size:10px;">
        <thead><tr style="background:rgba(255,255,255,0.04);">
            <th style="padding:6px 8px;text-align:left;color:rgba(255,255,255,0.4);font-weight:700;font-size:9px;text-transform:uppercase;letter-spacing:1px;">Employee</th>
            <th style="padding:6px 8px;text-align:left;color:rgba(255,255,255,0.4);font-weight:700;font-size:9px;text-transform:uppercase;letter-spacing:1px;">Position</th>
            <th style="padding:6px 8px;text-align:left;color:rgba(255,255,255,0.4);font-weight:700;font-size:9px;text-transform:uppercase;letter-spacing:1px;">Dept</th>
            <th style="padding:6px 8px;text-align:left;color:rgba(255,255,255,0.4);font-weight:700;font-size:9px;text-transform:uppercase;letter-spacing:1px;">Supervisor</th>
        </tr></thead>
        <tbody>${rows.slice(0, 20).map((r, i) => `
            <tr style="border-top:1px solid rgba(255,255,255,0.03);background:${i % 2 ? 'rgba(255,255,255,0.01)' : 'transparent'};">
                <td style="padding:5px 8px;color:#fff;font-weight:500;">${r.employeeName}<div style="font-size:8px;color:rgba(255,255,255,0.3);font-family:monospace;">ID: ${r.employeeId} | Num: ${r.employeeNumber}</div></td>
                <td style="padding:5px 8px;color:rgba(255,255,255,0.6);">${r.position}</td>
                <td style="padding:5px 8px;color:rgba(255,255,255,0.5);">${r.department}</td>
                <td style="padding:5px 8px;color:rgba(167,139,250,0.8);font-family:monospace;font-size:9px;">
                    Immed: ${r.immediateSupervisorId || '—'}<br>
                    Mgr: ${r.managerSupervisorId || '—'}
                </td>
            </tr>`).join('')}
            ${rows.length > 20 ? `<tr><td colspan="4" style="padding:8px;text-align:center;color:rgba(255,255,255,0.3);font-size:9px;">...and ${rows.length - 20} more employees</td></tr>` : ''}
        </tbody>
    </table>`;
    previewDiv.innerHTML = tableHTML;
    details.appendChild(previewDiv);
}

function hrisCommitImport() {
    if (hrisState.pendingImportRows.length === 0) return;
    
    const employees = hrisState.pendingImportRows;
    const finalEmployees = [...employees];

    // Find all supervisor IDs in the imported roster
    const supervisorIds = new Set();
    employees.forEach(emp => {
        if (emp.immediateSupervisorId) {
            supervisorIds.add(emp.immediateSupervisorId);
        }
    });

    // Group contributors reporting to each supervisor
    supervisorIds.forEach(supId => {
        const supervisor = employees.find(e => e.employeeId === supId);
        if (!supervisor) return;

        // Find reports of this supervisor who are contributors (have no reports themselves)
        const reports = employees.filter(e => e.immediateSupervisorId === supId);
        const contributors = reports.filter(e => !supervisorIds.has(e.employeeId));

        // Group only if there are 2 or more contributors
        if (contributors.length >= 2) {
            const teamNodeId = `team-node-${supervisor.employeeId}`;
            const teamName = `${supervisor.department || 'General'} Team`;

            // Create explicit Team Node
            finalEmployees.push({
                employeeId: teamNodeId,
                employeeName: teamName,
                position: 'Team Node',
                department: supervisor.department || 'General',
                immediateSupervisorId: supervisor.employeeId,
                employeeNumber: teamNodeId,
                managerSupervisorId: supervisor.immediateSupervisorId || null,
                isSupervisor: false,
                isTeamNode: true,
                column: 3
            });

            // Re-point contributors to the new Team Node
            contributors.forEach(c => {
                c.immediateSupervisorId = teamNodeId;
                c.managerSupervisorId = supervisor.employeeId;
                c.column = 4;
            });
        }
    });

    hrisState.employees = finalEmployees;
    hrisState.pendingImportRows = [];
    hrisState.selectedEmployeeId = null;
    hrisState.collapsedTeams.clear();
    hrisCloseImportModal();
    hrisPopulateDeptFilter();
    hrisRenderCanvas();
    if (typeof showToast === 'function') showToast(`Imported ${hrisState.employees.length} employees successfully!`);
}

/* ---- DATA BRIDGE (for app.js) ---- */
/**
 * Returns all employees. Called by app.js on "Save Organizational Structure".
 */
function hrisGetEmployees() {
    return hrisState.employees;
}

/**
 * Converts HRIS employees → app.js state.nodes/connections format.
 * Emits CEO/Root nodes + Manager nodes, plus Team nodes containing members.
 */
function hrisConvertToAppNodes() {
    const nodes = [];
    const connections = [];

    // 1. Add all Position Nodes (CEO, Managers, Sub-Managers, Solo Contributors)
    hrisState.employees.forEach(emp => {
        if (emp.isTeamNode) return;

        // Check if this reports to a Team Node
        const supervisor = emp.immediateSupervisorId ? hrisState.employees.find(x => x.employeeId === emp.immediateSupervisorId) : null;
        if (supervisor && supervisor.isTeamNode) {
            // This is a team member, it will be exported inside the team node's teammembers array
            return;
        }

        const isRoot = !emp.immediateSupervisorId;
        const tier = hrisGetTier(emp);
        const clearance = tier >= 4.0 ? 'Executive' : tier >= 3.0 ? 'Manager' : tier >= 2.0 ? 'Associate' : 'Restricted';
        
        nodes.push({
            id: emp.employeeId,
            name: emp.employeeName,
            column: emp.column || (isRoot ? 1 : 3),
            properties: {
                tax: `${emp.position} Compliance Stack`,
                benefits: `${emp.department} Standard Benefits`,
                security: `${clearance} Access Scope`,
                clearance: clearance,
                employee_number: emp.employeeNumber || emp.employeeId,
                manager_supervisor_id: emp.managerSupervisorId || ''
            },
            subitems: [],
            positionTitle: emp.position,
            department: emp.department,
            employeeId: emp.employeeId
        });

        // Connect to parent supervisor
        if (emp.immediateSupervisorId) {
            connections.push({
                from: emp.immediateSupervisorId,
                to: emp.employeeId
            });
        }
    });

    // 2. Add all Team Nodes
    hrisState.employees.forEach(teamNode => {
        if (!teamNode.isTeamNode) return;

        // Find all team members reporting to this Team Node
        const contributors = hrisState.employees.filter(emp => emp.immediateSupervisorId === teamNode.employeeId);

        nodes.push({
            id: teamNode.employeeId,
            name: teamNode.employeeName,
            column: 5, // Use Column 5 for Team Nodes
            properties: {
                tax: `${teamNode.department || 'General'} Team Tax Stack`,
                benefits: 'Team Performance Allowance',
                security: 'Shared Team Space Access',
                clearance: 'Associate'
            },
            teammembers: contributors.map(c => ({
                name: c.employeeName,
                emp_id: c.employeeNumber || c.employeeId,
                job: c.position,
                manager_supervisor_id: c.managerSupervisorId || ''
            }))
        });

        // Connect supervisor to their team node
        if (teamNode.immediateSupervisorId) {
            connections.push({
                from: teamNode.immediateSupervisorId,
                to: teamNode.employeeId
            });
        }
    });

    // ColumnNames and ColumnTypes definitions
    const columnNames = {
        1: '[ COLUMN 1: LEADERSHIP ]',
        2: '[ COLUMN 2: MANAGERS ]',
        3: '[ COLUMN 3: STAFF ]'
    };
    const columnTypes = {
        1: 'position',
        2: 'position',
        3: 'position'
    };

    return { nodes, connections, columnNames, columnTypes };
}

/* ---- INIT ---- */
function hrisInitCanvas(orgName) {
    hrisState.orgName = orgName || 'Organization';
    
    // Clear collapsed teams to start
    hrisState.collapsedTeams.clear();

    // Clear initial employees so the dashboard starts empty
    hrisState.employees = [];

    // Ingest existing nodes/connections from global state if they exist
    if (window.state && window.state.nodes && window.state.nodes.length > 0) {
        const tempEmployees = [];
        
        // 1. First pass: parse all position nodes
        window.state.nodes.forEach(node => {
            if (node.column !== 5) { // Position node (CEO, Manager, Sub-Manager, Solo Contributor)
                const props = node.properties || {};
                const empNum = props.employee_number || node.employeeId || node.id;
                const mgrSupId = props.manager_supervisor_id || null;
                const clearance = props.clearance || 'Associate';
                
                tempEmployees.push({
                    employeeId: node.employeeId || node.id,
                    employeeName: node.name,
                    position: node.positionTitle || (props.tax ? props.tax.replace(" Compliance Stack", "") : 'Position Title'),
                    department: node.department || (props.benefits ? props.benefits.replace(" Standard Benefits", "") : 'General'),
                    immediateSupervisorId: null, // Will resolve from connections
                    employeeNumber: empNum,
                    managerSupervisorId: mgrSupId,
                    isSupervisor: node.column === 1 || node.column === 2, // CEO/Managers are supervisors
                    hasTeamGroup: false
                });
            }
        });
        
        // 2. Second pass: parse connections between position nodes
        const connections = window.state.connections || [];
        connections.forEach(conn => {
            const child = tempEmployees.find(e => e.employeeId === conn.to);
            const parent = tempEmployees.find(e => e.employeeId === conn.from);
            if (child && parent) {
                child.immediateSupervisorId = parent.employeeId;
                parent.isSupervisor = true;
            }
        });
        
        // 3. Third pass: parse team nodes (column === 5)
        window.state.nodes.forEach(node => {
            if (node.column === 5) { // Team node
                // The supervisor ID is connected from the supervisor to this team node
                const conn = connections.find(c => c.to === node.id);
                const supervisorId = conn ? conn.from : null;
                const supervisor = supervisorId ? tempEmployees.find(e => e.employeeId === supervisorId) : null;
                
                // Add the explicit Team Node
                tempEmployees.push({
                    employeeId: node.id,
                    employeeName: node.name,
                    position: 'Team Node',
                    department: supervisor ? supervisor.department : 'General',
                    immediateSupervisorId: supervisorId,
                    employeeNumber: node.id,
                    managerSupervisorId: supervisor ? supervisor.immediateSupervisorId : null,
                    isSupervisor: false,
                    isTeamNode: true,
                    column: 3
                });
                
                // Add the team members pointing to the Team Node's ID
                const members = node.teammembers || [];
                members.forEach(member => {
                    const empId = member.emp_id || hrisGenId();
                    tempEmployees.push({
                        employeeId: empId,
                        employeeName: member.name,
                        position: member.job,
                        department: supervisor ? supervisor.department : 'General',
                        immediateSupervisorId: node.id,
                        employeeNumber: member.emp_id || empId,
                        managerSupervisorId: member.manager_supervisor_id || (supervisor ? supervisor.immediateSupervisorId : null),
                        isSupervisor: false,
                        isTeamNode: false,
                        column: 4
                    });
                });
            }
        });
        
        hrisState.employees = tempEmployees;
    }

    hrisPopulateDeptFilter();
    hrisRenderCanvas();
    hrisInitPan();
    hrisSetupAddEmployeeDropdown();


    // Button bindings
    document.getElementById('hris-add-employee-btn')?.addEventListener('click', hrisAddNewEmployee);
    document.getElementById('hris-template-btn')?.addEventListener('click', hrisTriggerDownloadTemplate);
    document.getElementById('hris-export-btn')?.addEventListener('click', hrisExportCSV);
    document.getElementById('hris-reset-btn')?.addEventListener('click', () => {
        if (confirm('Clear all employees from the canvas?')) {
            hrisState.employees = [];
            hrisState.selectedEmployeeId = null;
            hrisState.collapsedTeams.clear();
            document.getElementById('hris-sidebar')?.classList.remove('visible');
            hrisPopulateDeptFilter();
            hrisRenderCanvas();
        }
    });
    document.getElementById('hris-import-btn')?.addEventListener('click', hrisOpenImportModal);
    document.getElementById('hris-modal-close')?.addEventListener('click', hrisCloseImportModal);
    document.getElementById('hris-sidebar-close')?.addEventListener('click', hrisCloseSidebar);
    document.getElementById('hris-save-emp-btn')?.addEventListener('click', hrisSaveEmployee);
    document.getElementById('hris-delete-emp-btn')?.addEventListener('click', hrisDeleteEmployee);
    document.getElementById('hris-zoom-in')?.addEventListener('click', () => hrisSetZoom(hrisState.zoomScale + 0.1));
    document.getElementById('hris-zoom-out')?.addEventListener('click', () => hrisSetZoom(hrisState.zoomScale - 0.1));
    document.getElementById('hris-zoom-reset')?.addEventListener('click', () => { hrisState.panX = 0; hrisState.panY = 0; hrisSetZoom(1.0); });
    document.getElementById('hris-search-input')?.addEventListener('input', hrisRenderCanvas);
    document.getElementById('hris-dept-filter')?.addEventListener('change', hrisRenderCanvas);
    document.getElementById('hris-canvas-cancel-btn')?.addEventListener('click', () => {
        if (confirm('Cancel and go back to Step 1?')) {
            if (typeof navigateToStep === 'function') navigateToStep(1);
        }
    });

    // Click off sidebar to close it
    document.getElementById('hris-canvas-viewport')?.addEventListener('click', (e) => {
        if (!e.target.closest('.hris-node-card') && !e.target.closest('#hris-sidebar')) {
            hrisCloseSidebar();
        }
    });

    // Redraw connectors on window resize
    window.addEventListener('resize', () => requestAnimationFrame(hrisDrawConnections));
}
