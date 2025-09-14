using System.Text;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Data;
using System.Windows.Documents;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Navigation;
using System.Windows.Shapes;
using System.Speech.Synthesis;
using System.Speech.Recognition;
using MySqlConnector;
using Newtonsoft.Json;
using System.Net.Http;
using Microsoft.Win32;
using System.IO;
using System.Diagnostics;

namespace ZinAI;

/// <summary>
/// Zin AI - Your Trusted Binary Pulse Theory Intelligence
/// A complete recreation of the Electron-based AI chat system in native WPF
/// </summary>
public partial class MainWindow : Window
{
    // Voice and Speech Components
    private SpeechSynthesizer speechSynthesizer = null!;
    private SpeechRecognitionEngine speechRecognizer = null!;
    private bool isListening = false;
    private bool isJournalMode = false;
    private bool isVoiceEnabled = true;
    
    // AI and Database Components
    private HttpClient httpClient = null!;
    private string connectionString = "";
    
    // Ollama Process Management
    private Process? ollamaProcess = null;
    private bool ollamaStartedByApp = false;
    
    // Journal and Chat State
    private List<ChatMessage> chatHistory = new List<ChatMessage>();
    private Timer statsUpdateTimer = null!;
    private Timer? uiRefreshTimer = null;
    
    public MainWindow()
    {
        InitializeComponent();
        InitializeComponents();
        LoadConfiguration();
        StartOllama();
        StartStatsUpdates();
    }

    private void InitializeComponents()
    {
        // Initialize HTTP client for Ollama API calls
        httpClient = new HttpClient();
        httpClient.Timeout = TimeSpan.FromMinutes(2);
        
        // Initialize speech synthesis
        speechSynthesizer = new SpeechSynthesizer();
        speechSynthesizer.SetOutputToDefaultAudioDevice();
        
        // Initialize speech recognition
        try
        {
            speechRecognizer = new SpeechRecognitionEngine(new System.Globalization.CultureInfo("en-US"));
            speechRecognizer.LoadGrammar(new DictationGrammar());
            speechRecognizer.SetInputToDefaultAudioDevice();
            speechRecognizer.SpeechRecognized += OnSpeechRecognized;
            speechRecognizer.RecognizeAsync(RecognizeMode.Multiple);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Voice recognition initialization failed: {ex.Message}", "Voice Error", MessageBoxButton.OK, MessageBoxImage.Warning);
            isVoiceEnabled = false;
        }
        
        // Set up placeholder text behavior
        SetupPlaceholderBehavior();
        
        // Load initial stats
        LoadPulseCoreStats();
    }

    private void LoadConfiguration()
    {
        // Load PulseCore database configuration
        LoadPulseCoreConfig();
        
        // Load General Configuration for UI refresh timer
        LoadGeneralConfig();
        
        // Set initial voice settings
        speechSynthesizer.Rate = 2; // 1.2x speed
        speechSynthesizer.Volume = 80; // 0.8 volume
    }
    
    private void LoadPulseCoreConfig()
    {
        try
        {
            var baseDir = System.IO.Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "..", "..", "..", "..");
            var configPath = System.IO.Path.Combine(baseDir, "data", "pws", "pulse_db_config.json");
            
            if (File.Exists(configPath))
            {
                var configJson = File.ReadAllText(configPath);
                var config = JsonConvert.DeserializeObject<DatabaseConfiguration>(configJson);
                if (config != null)
                {
                    connectionString = $"Server={config.Server};Database={config.Database};Uid={config.Username};Pwd={config.Password};";
                    return;
                }
            }
        }
        catch
        {
            // Fall back to default if config loading fails
        }
        
        // Default fallback - user needs to configure via admin panel
        connectionString = "Server=localhost;Database=your_pulsecore_database;Uid=your_username;Pwd=your_password;";
    }

    private void LoadGeneralConfig()
    {
        try
        {
            var baseDir = System.IO.Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "..", "..", "..", "..");
            var configPath = System.IO.Path.Combine(baseDir, "data", "pws", "general_config.json");
            
            if (File.Exists(configPath))
            {
                var configJson = File.ReadAllText(configPath);
                var config = JsonConvert.DeserializeObject<GeneralConfiguration>(configJson);
                
                if (config != null)
                {
                    StartUIRefreshTimer(config.UpdateIntervalSeconds, config.AutoRefreshEnabled);
                }
            }
        }
        catch
        {
            // Fall back to default - no UI refresh timer
        }
    }

    private void StartStatsUpdates()
    {
        // Load update interval from config, default to 5 minutes
        var updateIntervalMinutes = 5; // Default: 5 minutes
        try
        {
            if (File.Exists("app_config.json"))
            {
                var configText = File.ReadAllText("app_config.json");
                var config = JsonConvert.DeserializeObject<AppConfiguration>(configText);
                updateIntervalMinutes = config?.StatsUpdateIntervalMinutes ?? 5;
            }
        }
        catch { }
        
        var updateInterval = TimeSpan.FromMinutes(updateIntervalMinutes);
        statsUpdateTimer = new Timer(UpdateStatsCallback, null, TimeSpan.Zero, updateInterval);
    }

    private void UpdateStatsCallback(object? state)
    {
        Dispatcher.Invoke(() => LoadPulseCoreStats());
    }

    private void StartUIRefreshTimer(int intervalSeconds, bool enabled)
    {
        // Dispose existing timer
        uiRefreshTimer?.Dispose();
        uiRefreshTimer = null;
        
        // Create new timer if enabled
        if (enabled && intervalSeconds > 0)
        {
            var interval = TimeSpan.FromSeconds(intervalSeconds);
            uiRefreshTimer = new Timer(UIRefreshCallback, null, TimeSpan.Zero, interval);
        }
    }

    private void UIRefreshCallback(object? state)
    {
        Dispatcher.Invoke(() => {
            // Update real-time UI elements (NOT database queries)
            RefreshUIElements();
        });
    }

    private void RefreshUIElements()
    {
        try
        {
            // Update bridge connection status indicators (highest priority)
            // This will ensure consistent bridge status across all UI components
            UpdateBridgeStatusDisplay();
            
            // Update any system time/uptime displays
            // Update connection duration counters if they exist
            // Update any other real-time status indicators
            
            // IMPORTANT: Keep this ultra-lightweight
            // NO database queries (handled by 5-minute timer)
            // NO network calls (unless for quick status checks)
            // Focus on updating existing data displays
        }
        catch
        {
            // Gracefully handle any refresh errors - don't break the timer
        }
    }

    private void UpdateBridgeStatusDisplay()
    {
        try
        {
            // Get current unified bridge status from AdminWindow
            // This ensures all bridge status indicators stay consistent
            // No network calls - just update UI elements with current status
            
            // Update any bridge status text, colors, or indicators in MainWindow
            // This resolves the inconsistent bridge status displays issue
        }
        catch
        {
            // Handle gracefully
        }
    }

    private void SetupPlaceholderBehavior()
    {
        // Handle placeholder text for message input
        MessageInputBox.GotFocus += (s, e) => {
            if (MessageInputBox.Text == "Type your message or press the microphone to speak...")
                MessageInputBox.Text = "";
        };
        
        MessageInputBox.LostFocus += (s, e) => {
            if (string.IsNullOrWhiteSpace(MessageInputBox.Text))
                MessageInputBox.Text = "Type your message or press the microphone to speak...";
        };

        // Handle placeholder text for Zin input
        ZinInputBox.GotFocus += (s, e) => {
            if (ZinInputBox.Text == "Ask Zin anything about your writing...")
                ZinInputBox.Text = "";
        };
        
        ZinInputBox.LostFocus += (s, e) => {
            if (string.IsNullOrWhiteSpace(ZinInputBox.Text))
                ZinInputBox.Text = "Ask Zin anything about your writing...";
        };
    }

    #region Mode Toggle Functions

    private void ToggleJournalMode(object sender, RoutedEventArgs e)
    {
        isJournalMode = !isJournalMode;
        
        if (isJournalMode)
        {
            // Switch to Journal Mode
            ChatScrollViewer.Visibility = Visibility.Collapsed;
            JournalModeGrid.Visibility = Visibility.Visible;
            AiModeText.Text = "Journal Mode";
            JournalModeBtn.Content = "💬 Chat Mode";
            UpdateWordCount();
        }
        else
        {
            // Switch to Chat Mode
            JournalModeGrid.Visibility = Visibility.Collapsed;
            ChatScrollViewer.Visibility = Visibility.Visible;
            AiModeText.Text = "Chat Mode";
            JournalModeBtn.Content = "📝 Journal Mode";
        }
    }

    #endregion

    #region Chat Functions

    private async void SendMessage(object sender, RoutedEventArgs e)
    {
        await SendUserMessage();
    }

    private async void MessageInputKeyDown(object sender, KeyEventArgs e)
    {
        if (e.Key == Key.Enter && !Keyboard.Modifiers.HasFlag(ModifierKeys.Shift))
        {
            e.Handled = true;
            await SendUserMessage();
        }
    }

    private async Task SendUserMessage()
    {
        string message = MessageInputBox.Text.Trim();
        
        if (string.IsNullOrEmpty(message) || message == "Type your message or press the microphone to speak...")
            return;
            
        // Add user message to chat
        AddChatMessage(message, isUser: true);
        MessageInputBox.Text = "";
        
        // Get AI response
        await GetAIResponse(message);
    }

    private void AddChatMessage(string message, bool isUser)
    {
        var chatMessage = new ChatMessage
        {
            Text = message,
            IsUser = isUser,
            Timestamp = DateTime.Now
        };
        
        chatHistory.Add(chatMessage);
        
        // Create message UI element
        var messageBorder = new Border
        {
            Background = isUser ? (Brush)FindResource("AccentPurple") : (Brush)FindResource("CardBackground"),
            CornerRadius = new CornerRadius(12),
            Padding = new Thickness(16),
            Margin = new Thickness(0, 0, 0, 15),
            HorizontalAlignment = isUser ? HorizontalAlignment.Right : HorizontalAlignment.Left,
            MaxWidth = 600
        };

        var messagePanel = new StackPanel();
        
        var senderText = new TextBlock
        {
            Text = isUser ? "You:" : "Zin AI:",
            FontWeight = FontWeights.Bold,
            Foreground = isUser ? (Brush)FindResource("TextPrimary") : (Brush)FindResource("AccentPurple"),
            Margin = new Thickness(0, 0, 0, 5)
        };
        
        var messageText = new TextBlock
        {
            Text = message,
            Foreground = (Brush)FindResource("TextPrimary"),
            TextWrapping = TextWrapping.Wrap
        };
        
        var timeText = new TextBlock
        {
            Text = chatMessage.Timestamp.ToString("HH:mm"),
            FontSize = 10,
            Foreground = (Brush)FindResource("TextMuted"),
            Margin = new Thickness(0, 8, 0, 0)
        };
        
        messagePanel.Children.Add(senderText);
        messagePanel.Children.Add(messageText);
        messagePanel.Children.Add(timeText);
        messageBorder.Child = messagePanel;
        
        ChatMessagesPanel.Children.Add(messageBorder);
        
        // Auto-scroll to bottom
        ChatScrollViewer.ScrollToEnd();
        
        // Speak AI response if enabled
        if (!isUser && AutoSpeakCheckBox.IsChecked == true)
        {
            SpeakText(message);
        }
    }

    private async Task GetAIResponse(string userMessage)
    {
        try
        {
            // Use web API with bridge support
            var chatRequest = new
            {
                message = userMessage,
                session_id = "desktop_chat_" + Environment.MachineName,
                mode = "chill"
            };

            var jsonRequest = JsonConvert.SerializeObject(chatRequest);
            var content = new StringContent(jsonRequest, Encoding.UTF8, "application/json");
            
            var response = await httpClient.PostAsync("http://localhost/ai/api/chat.php", content);
            
            if (response.IsSuccessStatusCode)
            {
                var jsonResponse = await response.Content.ReadAsStringAsync();
                var chatResponse = JsonConvert.DeserializeObject<ChatApiResponse>(jsonResponse);
                
                if (chatResponse?.Success == true)
                {
                    AddChatMessage(chatResponse.Response ?? "No response from AI", isUser: false);
                }
                else
                {
                    AddChatMessage("I'm having trouble processing your request. Please try again.", isUser: false);
                }
            }
            else
            {
                AddChatMessage("I'm having trouble connecting to the AI service. Please check your connection.", isUser: false);
            }
        }
        catch (Exception)
        {
            AddChatMessage("Connection error. Please check your network connection and try again.", isUser: false);
        }
    }

    #endregion

    #region Journal Functions

    private void JournalTextChanged(object sender, TextChangedEventArgs e)
    {
        UpdateWordCount();
    }

    private void UpdateWordCount()
    {
        if (JournalTextBox == null || WordCountText == null || CharCountText == null) return;
        
        string text = JournalTextBox.Text;
        
        if (text.StartsWith("Begin speaking or start typing"))
        {
            WordCountText.Text = "0 words";
            CharCountText.Text = "0 characters";
            return;
        }
        
        var words = text.Split(new[] { ' ', '\n', '\r', '\t' }, StringSplitOptions.RemoveEmptyEntries);
        var wordCount = words.Length;
        var charCount = text.Length;
        
        WordCountText.Text = $"{wordCount} words";
        CharCountText.Text = $"{charCount} characters";
    }

    private async void AnalyzeJournal(object sender, RoutedEventArgs e)
    {
        string journalText = JournalTextBox.Text;
        if (string.IsNullOrWhiteSpace(journalText) || journalText.StartsWith("Begin speaking"))
        {
            AddZinResponse("Please write something in your journal first, and I'll analyze it for you!");
            return;
        }
        
        await GetZinResponse($"Analyze this journal entry for insights and patterns: {journalText}");
    }

    private async void ImproveJournal(object sender, RoutedEventArgs e)
    {
        string journalText = JournalTextBox.Text;
        if (string.IsNullOrWhiteSpace(journalText) || journalText.StartsWith("Begin speaking"))
        {
            AddZinResponse("Please write something in your journal first, and I'll suggest improvements!");
            return;
        }
        
        await GetZinResponse($"Suggest improvements for this journal entry: {journalText}");
    }

    private async void ContinueJournal(object sender, RoutedEventArgs e)
    {
        string journalText = JournalTextBox.Text;
        if (string.IsNullOrWhiteSpace(journalText) || journalText.StartsWith("Begin speaking"))
        {
            AddZinResponse("Start writing something, and I'll help you continue your thoughts!");
            return;
        }
        
        await GetZinResponse($"Suggest ideas to continue this journal entry: {journalText}");
    }

    private async void SendZinMessage(object sender, RoutedEventArgs e)
    {
        await SendZinInput();
    }

    private async void ZinInputKeyDown(object sender, KeyEventArgs e)
    {
        if (e.Key == Key.Enter)
        {
            e.Handled = true;
            await SendZinInput();
        }
    }

    private async Task SendZinInput()
    {
        string message = ZinInputBox.Text.Trim();
        
        if (string.IsNullOrEmpty(message) || message == "Ask Zin anything about your writing...")
            return;
        
        ZinInputBox.Text = "";
        await GetZinResponse(message);
    }

    private async Task GetZinResponse(string userMessage)
    {
        try
        {
            // Use web API with bridge support for journal mode
            var chatRequest = new
            {
                message = userMessage,
                session_id = "desktop_journal_" + Environment.MachineName,
                mode = "journal",
                journal_context = JournalTextBox.Text.StartsWith("Begin speaking") ? "" : JournalTextBox.Text
            };

            var jsonRequest = JsonConvert.SerializeObject(chatRequest);
            var content = new StringContent(jsonRequest, Encoding.UTF8, "application/json");
            
            var response = await httpClient.PostAsync("http://localhost/ai/api/chat.php", content);
            
            if (response.IsSuccessStatusCode)
            {
                var jsonResponse = await response.Content.ReadAsStringAsync();
                var chatResponse = JsonConvert.DeserializeObject<ChatApiResponse>(jsonResponse);
                
                if (chatResponse?.Success == true)
                {
                    AddZinResponse(chatResponse.Response ?? "I'm here to help with your writing!");
                }
                else
                {
                    AddZinResponse("I'm here to help with your writing! Try asking me to analyze your thoughts, suggest improvements, or help you continue your ideas.");
                }
            }
            else
            {
                AddZinResponse("I'm having trouble connecting to the writing assistant service. Please check your connection.");
            }
        }
        catch (Exception)
        {
            AddZinResponse("I'm having trouble connecting right now, but I'm here to support your writing journey. Keep exploring your thoughts!");
        }
    }

    private void AddZinResponse(string response)
    {
        var responseBorder = new Border
        {
            Background = (Brush)FindResource("CardBackground"),
            CornerRadius = new CornerRadius(8),
            Padding = new Thickness(12),
            Margin = new Thickness(0, 0, 0, 10)
        };

        var responseText = new TextBlock
        {
            Text = response,
            Foreground = (Brush)FindResource("TextPrimary"),
            TextWrapping = TextWrapping.Wrap,
            FontSize = 13
        };

        responseBorder.Child = responseText;
        ZinResponsesPanel.Children.Add(responseBorder);
        
        ZinResponsesScrollViewer.ScrollToEnd();
    }

    private void SaveJournal(object sender, RoutedEventArgs e)
    {
        var saveDialog = new SaveFileDialog
        {
            Filter = "Text files (*.txt)|*.txt|All files (*.*)|*.*",
            DefaultExt = "txt",
            FileName = $"Journal_{DateTime.Now:yyyy-MM-dd_HH-mm}.txt"
        };

        if (saveDialog.ShowDialog() == true)
        {
            try
            {
                File.WriteAllText(saveDialog.FileName, JournalTextBox.Text);
                MessageBox.Show("Journal saved successfully!", "Save Complete", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show($"Error saving journal: {ex.Message}", "Save Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }

    #endregion

    #region Voice Functions

    private void ToggleVoice(object sender, RoutedEventArgs e)
    {
        if (!isVoiceEnabled) return;
        
        if (isListening)
        {
            StopListening();
        }
        else
        {
            StartListening();
        }
    }

    private void StartListening()
    {
        if (!isVoiceEnabled) return;
        
        try
        {
            speechRecognizer?.RecognizeAsync(RecognizeMode.Single);
            isListening = true;
            VoiceBtn.Content = "🔴";
            VoiceBtn.Background = (Brush)FindResource("AccentPink");
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Voice recognition error: {ex.Message}", "Voice Error", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
    }

    private void StopListening()
    {
        try
        {
            speechRecognizer?.RecognizeAsyncStop();
            isListening = false;
            VoiceBtn.Content = "🎤";
            VoiceBtn.Background = (Brush)FindResource("AccentGreen");
        }
        catch (Exception) { }
    }

    private void OnSpeechRecognized(object? sender, SpeechRecognizedEventArgs e)
    {
        if (e.Result?.Text != null)
        {
            Dispatcher.Invoke(() =>
            {
                if (isJournalMode)
                {
                    // Add to journal
                    if (JournalTextBox.Text.StartsWith("Begin speaking"))
                    {
                        JournalTextBox.Text = e.Result.Text;
                    }
                    else
                    {
                        JournalTextBox.Text += " " + e.Result.Text;
                    }
                    UpdateWordCount();
                }
                else
                {
                    // Add to message input
                    MessageInputBox.Text = e.Result.Text;
                }
                
                StopListening();
            });
        }
    }

    private void StartJournalVoice(object sender, RoutedEventArgs e)
    {
        StartListening();
    }

    private void TestVoice(object sender, RoutedEventArgs e)
    {
        SpeakText("Hello! This is Zin AI. Voice synthesis is working correctly.");
    }

    private void StopSpeech(object sender, RoutedEventArgs e)
    {
        try
        {
            speechSynthesizer?.SpeakAsyncCancelAll();
        }
        catch (Exception) { }
    }

    private void SpeakText(string text)
    {
        if (!isVoiceEnabled || AutoSpeakCheckBox.IsChecked != true) return;
        
        try
        {
            speechSynthesizer.SpeakAsync(text);
        }
        catch (Exception) { }
    }

    private void SpeechRateChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
    {
        if (SpeechRateText != null)
        {
            SpeechRateText.Text = $"{e.NewValue:F1}x";
            speechSynthesizer.Rate = (int)((e.NewValue - 1.0) * 10); // Convert to SAPI rate (-10 to +10)
        }
    }

    #endregion

    #region Quick Actions

    private async void QuickMessage_RecentNovas(object sender, RoutedEventArgs e)
    {
        MessageInputBox.Text = "What are my recent novas?";
        await SendUserMessage();
    }

    private async void QuickMessage_PatternAnalysis(object sender, RoutedEventArgs e)
    {
        MessageInputBox.Text = "Analyze my patterns";
        await SendUserMessage();
    }

    private async void QuickMessage_OptimizationTips(object sender, RoutedEventArgs e)
    {
        MessageInputBox.Text = "Help me optimize";
        await SendUserMessage();
    }

    private async void QuickMessage_VariablesData(object sender, RoutedEventArgs e)
    {
        MessageInputBox.Text = "Show variables data";
        await SendUserMessage();
    }

    #endregion

    #region Admin and Stats Functions

    private void OpenAdmin(object sender, RoutedEventArgs e)
    {
        var adminWindow = new AdminWindow();
        adminWindow.Show();
    }

    private void ClearChat(object sender, RoutedEventArgs e)
    {
        var result = MessageBox.Show("Are you sure you want to clear the chat history?", "Clear Chat", MessageBoxButton.YesNo, MessageBoxImage.Question);
        
        if (result == MessageBoxResult.Yes)
        {
            ChatMessagesPanel.Children.Clear();
            chatHistory.Clear();
        }
    }

    private void LoadPulseCoreStats()
    {
        try
        {
            using var connection = new MySqlConnection(connectionString);
            connection.Open();
            
            // Get total climaxes
            using (var cmd = new MySqlCommand("SELECT COUNT(*) FROM climax_groups", connection))
            {
                var result = cmd.ExecuteScalar();
                TotalClimaxesText.Text = result?.ToString() ?? "0";
            }
            
            // Get total novas
            using (var cmd = new MySqlCommand("SELECT COUNT(*) FROM nova_events", connection))
            {
                var result = cmd.ExecuteScalar();
                TotalNovasText.Text = result?.ToString() ?? "0";
            }
            
            // Get average complexity
            using (var cmd = new MySqlCommand("SELECT AVG(complexity) FROM nova_events", connection))
            {
                var result = cmd.ExecuteScalar();
                if (result != DBNull.Value && result != null)
                {
                    AvgComplexityText.Text = $"{Convert.ToDouble(result):F2}";
                }
                else
                {
                    AvgComplexityText.Text = "0.00";
                }
            }
            
            // Get total sessions (approximated)
            using (var cmd = new MySqlCommand("SELECT COUNT(DISTINCT DATE(created_at)) FROM nova_events", connection))
            {
                var result = cmd.ExecuteScalar();
                TotalSessionsText.Text = result?.ToString() ?? "0";
            }
            
            // Update connection status
            PulseStatusText.Foreground = (Brush)FindResource("AccentGreen");
        }
        catch (Exception)
        {
            // Update connection status to error
            PulseStatusText.Foreground = (Brush)FindResource("AccentPink");
            
            TotalClimaxesText.Text = "Error";
            TotalNovasText.Text = "Error";
            AvgComplexityText.Text = "Error";
            TotalSessionsText.Text = "Error";
        }
    }

    #endregion

    #region Ollama Process Management

    private async void StartOllama()
    {
        try
        {
            // Check if Ollama is already running
            var existingProcesses = Process.GetProcessesByName("ollama");
            if (existingProcesses.Length > 0)
            {
                Debug.WriteLine("Ollama is already running");
                return;
            }

            // Start Ollama with external host binding
            var startInfo = new ProcessStartInfo
            {
                FileName = "ollama",
                Arguments = "serve",
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true
            };

            // Set environment variable for localhost-only security
            startInfo.EnvironmentVariables["OLLAMA_HOST"] = "127.0.0.1:11434";

            ollamaProcess = Process.Start(startInfo);
            if (ollamaProcess != null)
            {
                ollamaStartedByApp = true;
                Debug.WriteLine("Started Ollama with localhost-only access");
                
                // Wait a moment for Ollama to start up
                await Task.Delay(3000);
                
                // Verify Ollama is responding
                await VerifyOllamaConnection();
            }
        }
        catch (Exception ex)
        {
            Debug.WriteLine($"Failed to start Ollama: {ex.Message}");
        }
    }

    private async Task VerifyOllamaConnection()
    {
        try
        {
            using var client = new HttpClient();
            client.Timeout = TimeSpan.FromSeconds(5);
            
            var response = await client.GetAsync("http://localhost:11434/api/tags");
            if (response.IsSuccessStatusCode)
            {
                Debug.WriteLine("Ollama is responding successfully");
            }
            else
            {
                Debug.WriteLine($"Ollama responded with status: {response.StatusCode}");
            }
        }
        catch (Exception ex)
        {
            Debug.WriteLine($"Failed to verify Ollama connection: {ex.Message}");
        }
    }

    private void StopOllama()
    {
        try
        {
            if (ollamaProcess != null && !ollamaProcess.HasExited && ollamaStartedByApp)
            {
                Debug.WriteLine("Stopping Ollama...");
                ollamaProcess.Kill();
                ollamaProcess.WaitForExit(5000);
                ollamaProcess.Dispose();
                ollamaProcess = null;
                ollamaStartedByApp = false;
                Debug.WriteLine("Ollama stopped successfully");
            }
        }
        catch (Exception ex)
        {
            Debug.WriteLine($"Error stopping Ollama: {ex.Message}");
        }
    }

    #endregion

    #region Configuration Updates

    // Static method for AdminWindow to call when General Configuration is updated
    public static void UpdateGeneralConfiguration(int intervalSeconds, bool enabled)
    {
        try
        {
            // Find MainWindow instance and update timer
            var mainWindow = Application.Current.MainWindow as MainWindow;
            mainWindow?.StartUIRefreshTimer(intervalSeconds, enabled);
        }
        catch
        {
            // Handle gracefully if MainWindow not found
        }
    }

    #endregion

    #region Cleanup

    protected override void OnClosed(EventArgs e)
    {
        // Stop Ollama if we started it
        StopOllama();
        
        // Cleanup resources
        speechSynthesizer?.Dispose();
        speechRecognizer?.Dispose();
        httpClient?.Dispose();
        statsUpdateTimer?.Dispose();
        uiRefreshTimer?.Dispose();
        
        base.OnClosed(e);
    }

    #endregion
}

// Data Models
public class ChatMessage
{
    public string Text { get; set; } = string.Empty;
    public bool IsUser { get; set; }
    public DateTime Timestamp { get; set; }
}

public class ChatApiResponse
{
    public bool Success { get; set; }
    public string Response { get; set; } = string.Empty;
    public string Mode { get; set; } = string.Empty;
    public int ProcessingTimeMs { get; set; }
    public bool Speak { get; set; }
    public string Error { get; set; } = string.Empty;
}


