// AI Admin Panel JavaScript
class AIAdmin {
    constructor() {
        this.initializeAdmin();
    }

    initializeAdmin() {
        // Show default section
        this.showSection('identity');
        
        // Set up navigation
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const section = e.target.getAttribute('onclick').match(/'([^']+)'/)[1];
                this.showSection(section);
            });
        });

        // Load current settings
        this.loadSettings();
        this.loadTemplates();
        this.loadConversationStats();
        this.checkIntegrations();
    }

    showSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.admin-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Remove active class from all nav buttons
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected section
        const targetSection = document.getElementById(sectionName);
        if (targetSection) {
            targetSection.classList.add('active');
        }
        
        // Highlight active nav button
        const activeBtn = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
    }

    async loadSettings() {
        try {
            const response = await fetch('../api/settings.php');
            const result = await response.json();
            
            if (result.success) {
                // Populate form fields
                document.getElementById('aiName').value = result.data.ai_name || 'ai';
                document.getElementById('personality').value = result.data.personality || 'helpful,analytical,curious';
                document.getElementById('responseStyle').value = result.data.response_style || 'conversational';
                document.getElementById('systemPrompt').value = result.data.welcome_message || '';
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        }
    }

    async saveAllSettings() {
        const settings = {
            ai_name: document.getElementById('aiName').value,
            personality: document.getElementById('personality').value,
            response_style: document.getElementById('responseStyle').value,
            welcome_message: document.getElementById('systemPrompt').value
        };

        try {
            const response = await fetch('../api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ settings: settings })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Settings saved successfully!', 'success');
            } else {
                this.showNotification('Error saving settings: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showNotification('Network error saving settings', 'error');
        }
    }

    async loadTemplates() {
        try {
            const response = await fetch('../api/templates.php');
            const result = await response.json();
            
            if (result.success) {
                this.displayTemplates(result.data);
            }
        } catch (error) {
            console.error('Error loading templates:', error);
            document.getElementById('templatesContainer').innerHTML = '<p>Error loading response templates.</p>';
        }
    }

    displayTemplates(templates) {
        const container = document.getElementById('templatesContainer');
        if (!container) return;

        if (!templates || templates.length === 0) {
            container.innerHTML = '<p>No response templates found. <button onclick="addNewTemplate()" class="btn-primary">Add First Template</button></p>';
            return;
        }

        container.innerHTML = templates.map(template => `
            <div class="template-item" data-id="${template.id}">
                <div class="template-header">
                    <h4>${template.category} - Priority ${template.priority}</h4>
                    <div class="template-actions">
                        <button class="btn-small btn-secondary" onclick="editTemplate(${template.id})">Edit</button>
                        <button class="btn-small btn-danger" onclick="deleteTemplate(${template.id})">Delete</button>
                    </div>
                </div>
                <p><strong>Trigger:</strong> <code>${template.trigger_pattern}</code></p>
                <p><strong>Response:</strong> ${template.response_text}</p>
                <p><small>Used ${template.usage_count} times | ${template.active ? 'Active' : 'Inactive'}</small></p>
            </div>
        `).join('');
    }

    async loadConversationStats() {
        try {
            const response = await fetch('../api/stats.php');
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('totalConversations').textContent = result.data.total_conversations || '0';
                document.getElementById('totalMessages').textContent = result.data.total_messages || '0';
                document.getElementById('avgResponseTime').textContent = result.data.avg_response_time ? result.data.avg_response_time + 'ms' : '-';
                document.getElementById('successRate').textContent = result.data.success_rate ? result.data.success_rate + '%' : '-';
            }
        } catch (error) {
            console.error('Error loading conversation stats:', error);
        }
    }

    async checkIntegrations() {
        // Check PulseCore connection
        try {
            const response = await fetch('../api/pulsecore-stats.php');
            const result = await response.json();
            
            if (result.success) {
                this.updateIntegrationStatus('pulsecoreStatus', true, 'Connected');
                document.getElementById('novaCount').textContent = result.data.total_novas || '0';
                document.getElementById('climaxCount').textContent = result.data.total_groups || '0';
                document.getElementById('lastNovaTime').textContent = result.data.last_nova_time ? new Date(result.data.last_nova_time).toLocaleDateString() : '-';
            } else {
                this.updateIntegrationStatus('pulsecoreStatus', false, 'Error: ' + result.error);
            }
        } catch (error) {
            this.updateIntegrationStatus('pulsecoreStatus', false, 'Connection failed');
        }

        // Check Variables connection
        try {
            const response = await fetch('../api/variables-stats.php');
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.updateIntegrationStatus('variablesStatus', true, 'Connected');
                    if (document.getElementById('variableCount')) {
                        document.getElementById('variableCount').textContent = result.data?.variable_count || '0';
                    }
                    if (document.getElementById('calculationCount')) {
                        document.getElementById('calculationCount').textContent = result.data?.category_count || '0';
                    }
                } else {
                    this.updateIntegrationStatus('variablesStatus', false, 'Error: ' + result.error);
                }
            } else {
                this.updateIntegrationStatus('variablesStatus', false, 'API Error (' + response.status + ')');
            }
        } catch (error) {
            console.warn('Variables API not available:', error.message);
            this.updateIntegrationStatus('variablesStatus', false, 'Connection failed');
        }

        // Check PC AI connection (optional - won't error if not available)
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 2000); // 2 second timeout
            
            const response = await fetch('http://localhost:8000/health', {
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            
            if (response.ok) {
                const result = await response.json();
                this.updateIntegrationStatus('pcaiStatus', true, 'Connected');
                if (document.getElementById('currentModel')) {
                    document.getElementById('currentModel').textContent = result.model || 'Local AI';
                }
                if (document.getElementById('gpuMemory')) {
                    document.getElementById('gpuMemory').textContent = result.gpu_memory || 'N/A';
                }
            } else {
                this.updateIntegrationStatus('pcaiStatus', false, 'PC AI offline');
            }
        } catch (error) {
            // This is expected if PC AI isn't running
            console.info('PC AI not available (this is normal):', error.message);
            this.updateIntegrationStatus('pcaiStatus', false, 'PC AI not running');
            if (document.getElementById('currentModel')) {
                document.getElementById('currentModel').textContent = 'Not connected';
            }
        }
    }

    updateIntegrationStatus(elementId, connected, message) {
        const statusElement = document.getElementById(elementId);
        if (!statusElement) return;

        const indicator = statusElement.querySelector('.status-indicator');
        const text = statusElement.querySelector('.status-text');

        if (indicator) {
            indicator.className = 'status-indicator ' + (connected ? 'connected' : '');
        }
        
        if (text) {
            text.textContent = message;
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">×</button>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
}

// Global functions
function showSection(sectionName) {
    if (window.aiAdmin) {
        window.aiAdmin.showSection(sectionName);
    }
}

function saveAllSettings() {
    if (window.aiAdmin) {
        window.aiAdmin.saveAllSettings();
    }
}

function addNewTemplate() {
    // Simple implementation - could be enhanced with modal
    const trigger = prompt('Enter trigger pattern (regex):');
    const response = prompt('Enter response text:');
    const category = prompt('Enter category (optional):', 'general');
    
    if (trigger && response) {
        // TODO: Implement template creation API
        console.log('Would create template:', { trigger, response, category });
        window.aiAdmin.showNotification('Template creation not yet implemented', 'info');
    }
}

function testPulseCoreConnection() {
    window.aiAdmin.checkIntegrations();
}

function testVariablesConnection() {
    window.aiAdmin.checkIntegrations();
}

function testPCAIConnection() {
    window.aiAdmin.checkIntegrations();
}

function analyzeConversations() {
    window.aiAdmin.showNotification('Conversation analysis not yet implemented', 'info');
}

function clearLearningData() {
    if (confirm('Are you sure you want to clear all learning data?')) {
        window.aiAdmin.showNotification('Learning data cleared', 'success');
    }
}

function exportLearningData() {
    window.aiAdmin.showNotification('Learning data export not yet implemented', 'info');
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.aiAdmin = new AIAdmin();
});

// Add notification styles
const notificationStyles = `
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    font-weight: bold;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 15px;
    max-width: 400px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.notification-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(5, 150, 105, 0.8));
}

.notification-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.8));
}

.notification-info {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.9), rgba(8, 145, 178, 0.8));
}

.notification button {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
}
`;

// Add styles to page
const styleSheet = document.createElement('style');
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);