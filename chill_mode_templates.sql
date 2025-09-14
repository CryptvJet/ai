-- Additional Response Templates for Enhanced Chill Mode
-- Add these templates to improve Chill Mode functionality

USE `vemite5_pulse-core-ai`;

-- Insert additional helpful response templates
INSERT IGNORE INTO ai_response_templates (trigger_pattern, response_text, category, priority) VALUES

-- Expanded greetings and social interactions
('good morning|morning', 'Good morning! I hope you\'re having a productive day with your simulations. What can I help you explore in your PulseCore data today?', 'greetings', 85),
('good evening|evening', 'Good evening! Perfect time to review today\'s simulation results. What patterns would you like me to analyze?', 'greetings', 85),
('how.*are.*you|how.*doing', 'I\'m doing well, thank you for asking! I\'m here and ready to help analyze your PulseCore data. How are your simulations going?', 'social', 80),
('goodbye|bye|see.*you', 'Goodbye! Feel free to come back anytime you want to analyze more data or discuss your simulation insights. Have a great day!', 'social', 75),

-- Enhanced help and capabilities
('what.*you.*know.*about', 'I know quite a bit about PulseCore simulations, nova analysis, pulse patterns, and variable calculations. I can help interpret your data even in Chill Mode. What specific topic interests you?', 'help', 80),
('explain|tell.*me.*about', 'I\'d be happy to explain! I can discuss PulseCore concepts, simulation patterns, nova analysis, and data insights. What would you like me to explain?', 'help', 75),
('show.*me|display', 'I can help analyze and interpret your data! While I can\'t generate live displays in Chill Mode, I can explain patterns and provide insights. What data would you like to explore?', 'help', 70),

-- PulseCore specific queries
('simulation.*result|result.*simulation', 'Let me help you understand your simulation results! I can analyze patterns, identify trends, and provide insights about your PulseCore data. What aspects interest you most?', 'pulsecore', 85),
('pulse.*pattern|pattern.*pulse', 'Pulse patterns are fascinating! I can help analyze rhythm variations, intensity changes, and correlations in your data. What specific patterns are you seeing?', 'pulsecore', 80),
('complexity.*level|level.*complexity', 'Complexity analysis is one of my specialties! I can help interpret complexity trends and their relationship to your other variables. What complexity patterns are you curious about?', 'analysis', 80),
('data.*analysis|analyze.*data', 'Data analysis is what I do best! Even in Chill Mode, I can help identify trends, explain correlations, and provide insights about your PulseCore simulations. What data needs attention?', 'analysis', 85),

-- Error and troubleshooting
('not.*working|broken|error', 'I understand you might be experiencing some issues. While my advanced capabilities may be limited right now, I can still help troubleshoot data questions and provide basic analysis. What specific problem can I help with?', 'troubleshooting', 70),
('slow|lag|delay', 'Performance can vary sometimes. I\'m still here to help with your PulseCore analysis and answer questions about your data. What would you like to explore while we wait for full capabilities?', 'troubleshooting', 65),

-- Learning and curiosity
('learn.*more|teach.*me|want.*to.*know', 'I love helping people learn! I can explain PulseCore concepts, discuss simulation theory, and help you understand your data patterns. What would you like to explore together?', 'educational', 80),
('interesting|curious|wonder', 'Curiosity is wonderful! There\'s always something interesting in PulseCore data - from unexpected nova patterns to subtle variable correlations. What has caught your attention?', 'educational', 75),

-- Time and session queries
('today|recent|lately', 'Recent activity is always interesting to analyze! I can help you understand patterns in your latest simulations and data. What recent trends would you like to explore?', 'temporal', 70),
('history|past|previous', 'Historical data provides great context! I can help analyze trends over time and compare past patterns with current results. What timeframe interests you?', 'temporal', 70),

-- Encouragement and motivation
('frustrated|stuck|confused', 'I understand that data analysis can be challenging sometimes. Let\'s work through this together! Even in Chill Mode, I can help clarify concepts and provide guidance. What\'s puzzling you?', 'supportive', 75),
('good.*job|well.*done|excellent', 'Thank you! I enjoy helping with your PulseCore analysis. Your curiosity and questions help make the data exploration more interesting. What shall we investigate next?', 'supportive', 70);

-- Update existing templates to be more specific and helpful
UPDATE ai_response_templates SET 
    response_text = 'Hello! I\'m Zin, your PulseCore AI assistant. I\'m currently in Chill Mode, which means I have access to your simulation data and can provide analysis, insights, and answer questions about your nova patterns, pulse variables, and complexity trends. What would you like to explore?'
WHERE trigger_pattern = 'hello|hi|hey';

UPDATE ai_response_templates SET 
    response_text = 'I can help you with many things even in Chill Mode! I can analyze your PulseCore simulations, explain nova patterns, discuss pulse variables, interpret complexity data, answer questions about your simulation results, and provide insights about trends. What specific area interests you?'
WHERE trigger_pattern = 'help|what.*can.*do';

-- Set higher priorities for most commonly used templates
UPDATE ai_response_templates SET priority = 95 WHERE category = 'greetings';
UPDATE ai_response_templates SET priority = 90 WHERE category = 'help';
UPDATE ai_response_templates SET priority = 85 WHERE category = 'pulsecore';
UPDATE ai_response_templates SET priority = 80 WHERE category = 'analysis';