/**
 * Product Backlog JavaScript - Modular Version
 * This version loads modules separately for better performance
 */

// Global route helper function
function route(name, params = {}) {
    const routes = {
        'tasks.get-components': '/get-components',
        'tasks.get-epics': '/get-epics',
        'tasks.get-stories': '/get-stories',
        'tasks.get-subtasks': '/get-subtasks',
        'tasks.get-bugs': '/get-bugs',
        'tasks.store': '/tasks/store',
        'tasks.update-children-sprint': '/tasks/TASK_ID_PLACEHOLDER/update-children-sprint',
        'product-backlog.update-children-sprint': '/product-backlog/TASK_ID_PLACEHOLDER/update-children-sprint',
        'sprints.store': '/sprints',
        'sprints.reopen': '/sprints/SPRINT_ID_PLACEHOLDER/reopen',
        'sprints.close': '/sprints/SPRINT_ID_PLACEHOLDER/close',
        'backlog.update-assigned-users': '/api/backlog/tasks/TASK_ID_PLACEHOLDER/update-assigned-users',
        'api.components-for-filter': '/api/components-for-filter',
        'api.epics-for-filter': '/api/epics-for-filter',
        'backlog.get-stories': '/api/backlog/stories',
        'backlog.get-bugs': '/api/backlog/bugs',
        'tasks.show': '/tasks/TASK_ID_PLACEHOLDER',
        'tasks.destroy': '/tasks/TASK_ID_PLACEHOLDER'
    };

    if (routes[name]) {
        let url = routes[name];
        // Replace ID placeholder with actual ID if provided
        if (params.id || params.taskId) {
            const taskId = params.id || params.taskId;
            url = url.replace('TASK_ID_PLACEHOLDER', taskId);
        }
        if (params.sprint) {
            const sprintId = params.sprint;
            url = url.replace('SPRINT_ID_PLACEHOLDER', sprintId);
        }
        return url;
    }

    console.error('Route not found: ' + name);
    return '#';
}

// Make route function globally available
window.route = route;

// Module loader with dependency management
class ModuleLoader {
    constructor() {
        this.loaded = new Set();
        this.loading = new Map();
        this.baseUrl = '/assets/js/modules/';
    }

    loadModule(name) {
        if (this.loaded.has(name)) {
            return Promise.resolve();
        }

        if (this.loading.has(name)) {
            return this.loading.get(name);
        }

        const promise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = this.baseUrl + name + '.js';
            script.async = true;

            script.onload = () => {
                this.loaded.add(name);
                this.loading.delete(name);
                resolve();
            };

            script.onerror = () => {
                this.loading.delete(name);
                reject(new Error(`Failed to load module: ${name}`));
            };

            document.head.appendChild(script);
        });

        this.loading.set(name, promise);
        return promise;
    }

    async loadAllModules() {
        try {
            // Load utils first as other modules may depend on it
            await this.loadModule('backlog-utils');

            // Load core modules in parallel
            await Promise.all([
                this.loadModule('backlog-renderer'),
                this.loadModule('backlog-task-loader'),
                this.loadModule('backlog-crud'),
                this.loadModule('backlog-assignment')
            ]);

            // Load main module last as it depends on others
            await this.loadModule('backlog-main');

            console.log('All backlog modules loaded successfully');
            return true;
        } catch (error) {
            console.error('Error loading modules:', error);
            return false;
        }
    }
}

// Initialize the module loader
document.addEventListener('DOMContentLoaded', async function() {
    const loader = new ModuleLoader();
    
    // Load CSS dependencies if needed
    const requiredStyles = [
        { id: 'feather-css', href: '/assets/css/feather.css' },
        { id: 'select2-css', href: '/assets/css/select2.min.css' }
    ];

    requiredStyles.forEach(style => {
        if (!document.getElementById(style.id)) {
            const link = document.createElement('link');
            link.id = style.id;
            link.rel = 'stylesheet';
            link.href = style.href;
            document.head.appendChild(link);
        }
    });

    try {
        const success = await loader.loadAllModules();
        if (success) {
            // Initialize the backlog system
            setTimeout(() => {
                if (window.BacklogMain && typeof window.BacklogMain.init === 'function') {
                    window.BacklogMain.init();
                } else {
                    console.error('BacklogMain module not found or init method not available');
                }
            }, 100);
        }
    } catch (error) {
        console.error('Failed to initialize product backlog:', error);
    }
});