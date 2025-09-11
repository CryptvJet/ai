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
    private string ollamaApiUrl = "http://localhost:11434/api/generate";
    private string connectionString = "";
    
    // Journal and Chat State
    private List<ChatMessage> chatHistory = new List<ChatMessage>();
    private Timer statsUpdateTimer = null!;
    
    public MainWindow()
    {
        InitializeComponent();
        InitializeComponents();
        LoadConfiguration();
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
        // Use the actual database credentials
        connectionString = "Server=localhost;Database=vemite5_pulse-core-ai;Uid=vemite5_p-core;Pwd=HHsJgdR6$ZMpV#F*;";
        
        // Set initial voice settings
        speechSynthesizer.Rate = 2; // 1.2x speed
        speechSynthesizer.Volume = 80; // 0.8 volume
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
            // Check if Ollama is available
            var ollamaRequest = new
            {
                model = "llama3.2:3b-instruct-q4_K_M",
                prompt = $"You are Zin AI, a specialized assistant for Binary Pulse Theory (BPT). You have direct access to PulseCore database and understand BPT concepts deeply. Respond to: {userMessage}",
                stream = false
            };

            var jsonRequest = JsonConvert.SerializeObject(ollamaRequest);
            var content = new StringContent(jsonRequest, Encoding.UTF8, "application/json");
            
            var response = await httpClient.PostAsync(ollamaApiUrl, content);
            
            if (response.IsSuccessStatusCode)
            {
                var jsonResponse = await response.Content.ReadAsStringAsync();
                var ollamaResponse = JsonConvert.DeserializeObject<OllamaResponse>(jsonResponse);
                
                AddChatMessage(ollamaResponse?.Response ?? "No response from AI", isUser: false);
            }
            else
            {
                AddChatMessage("I'm having trouble connecting to Ollama. Please check that Ollama is running and the model is loaded.", isUser: false);
            }
        }
        catch (Exception)
        {
            AddChatMessage("Connection error. Please check that Ollama is running and try again.", isUser: false);
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
            var ollamaRequest = new
            {
                model = "llama3.2:3b-instruct-q4_K_M",
                prompt = $"You are Zin, an AI writing companion specialized in helping with journal writing and creative expression. Be supportive, insightful, and encouraging. Respond to: {userMessage}",
                stream = false
            };

            var jsonRequest = JsonConvert.SerializeObject(ollamaRequest);
            var content = new StringContent(jsonRequest, Encoding.UTF8, "application/json");
            
            var response = await httpClient.PostAsync(ollamaApiUrl, content);
            
            if (response.IsSuccessStatusCode)
            {
                var jsonResponse = await response.Content.ReadAsStringAsync();
                var ollamaResponse = JsonConvert.DeserializeObject<OllamaResponse>(jsonResponse);
                
                AddZinResponse(ollamaResponse?.Response ?? "I'm here to help with your writing!");
            }
            else
            {
                AddZinResponse("I'm here to help with your writing! Try asking me to analyze your thoughts, suggest improvements, or help you continue your ideas.");
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
            PcStatusText.Foreground = (Brush)FindResource("AccentGreen");
        }
        catch (Exception)
        {
            // Update connection status to error
            PulseStatusText.Foreground = (Brush)FindResource("AccentPink");
            PcStatusText.Foreground = (Brush)FindResource("AccentPink");
            
            TotalClimaxesText.Text = "Error";
            TotalNovasText.Text = "Error";
            AvgComplexityText.Text = "Error";
            TotalSessionsText.Text = "Error";
        }
    }

    #endregion

    #region Cleanup

    protected override void OnClosed(EventArgs e)
    {
        // Cleanup resources
        speechSynthesizer?.Dispose();
        speechRecognizer?.Dispose();
        httpClient?.Dispose();
        statsUpdateTimer?.Dispose();
        
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

public class OllamaResponse
{
    public string Response { get; set; } = string.Empty;
    public bool Done { get; set; }
}

public class AppConfiguration
{
    public int StatsUpdateIntervalMinutes { get; set; } = 5;
    public bool AutoStartVoice { get; set; } = false;
    public bool MinimizeToTray { get; set; } = false;
}