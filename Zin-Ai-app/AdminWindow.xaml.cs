using System.Windows;
using System.Windows.Controls;
using MySqlConnector;
using Newtonsoft.Json;
using System.Net.Http;
using System.Text;
using System.IO;
using Microsoft.Win32;

namespace ZinAI;

/// <summary>
/// Admin Panel for Zin AI Configuration
/// </summary>
public partial class AdminWindow : Window
{
    private HttpClient httpClient;
    
    public AdminWindow()
    {
        InitializeComponent();
        httpClient = new HttpClient();
        httpClient.Timeout = TimeSpan.FromSeconds(30);
        LoadCurrentConfiguration();
        UpdateSystemInfo();
    }

    private void LoadCurrentConfiguration()
    {
        // Load current configuration values
        // These would typically come from a configuration file
        
        // Update system info
        DotNetVersionText.Text = Environment.Version.ToString();
        
        try
        {
            // Check if speech is available
            var speechSynthesizer = new System.Speech.Synthesis.SpeechSynthesizer();
            SpeechEngineText.Text = "Windows Speech Platform (Available)";
            speechSynthesizer.Dispose();
        }
        catch
        {
            SpeechEngineText.Text = "Windows Speech Platform (Not Available)";
        }
    }

    private void UpdateSystemInfo()
    {
        LastDbConnectionText.Text = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
        // TotalMessagesText would be updated from actual message count
    }

    #region AI Configuration

    private void SaveAIConfiguration(object sender, RoutedEventArgs e)
    {
        try
        {
            var config = new AIConfiguration
            {
                Name = AiNameInput.Text,
                Personality = ((ComboBoxItem)PersonalityComboBox.SelectedItem)?.Content?.ToString() ?? "Friendly Assistant",
                SystemPrompt = SystemPromptInput.Text
            };
            
            // Save to configuration file
            var configJson = JsonConvert.SerializeObject(config, Formatting.Indented);
            File.WriteAllText("ai_config.json", configJson);
            
            MessageBox.Show("AI configuration saved successfully!", "Configuration Saved", 
                          MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Error saving AI configuration: {ex.Message}", "Save Error", 
                          MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    #endregion

    #region Database Configuration

    private async void TestDatabaseConnection(object sender, RoutedEventArgs e)
    {
        try
        {
            DbStatusText.Text = "Testing connection...";
            DbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            
            var connectionString = $"Server={DbServerInput.Text};Database={DbNameInput.Text};Uid={DbUserInput.Text};Pwd={DbPasswordInput.Password};";
            
            using var connection = new MySqlConnection(connectionString);
            await connection.OpenAsync();
            
            // Test a simple query
            using var cmd = new MySqlCommand("SELECT VERSION()", connection);
            var version = await cmd.ExecuteScalarAsync();
            
            DbStatusText.Text = $"✅ Connection successful! MySQL Version: {version}";
            DbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            
            UpdateSystemInfo();
        }
        catch (Exception ex)
        {
            DbStatusText.Text = $"❌ Connection failed: {ex.Message}";
            DbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }
    }

    private void SaveDatabaseConfiguration(object sender, RoutedEventArgs e)
    {
        try
        {
            var config = new DatabaseConfiguration
            {
                Server = DbServerInput.Text,
                Database = DbNameInput.Text,
                Username = DbUserInput.Text,
                Password = DbPasswordInput.Password
            };
            
            // Save to configuration file
            var configJson = JsonConvert.SerializeObject(config, Formatting.Indented);
            File.WriteAllText("db_config.json", configJson);
            
            MessageBox.Show("Database configuration saved successfully!", "Configuration Saved", 
                          MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Error saving database configuration: {ex.Message}", "Save Error", 
                          MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    #endregion

    #region AI Model Configuration

    private void TemperatureChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
    {
        if (TemperatureText != null)
        {
            TemperatureText.Text = e.NewValue.ToString("F1");
        }
    }

    private async void TestAIConnection(object sender, RoutedEventArgs e)
    {
        try
        {
            AiStatusText.Text = "Testing AI connection...";
            AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            
            var testRequest = new
            {
                model = ModelNameInput.Text,
                prompt = "Hello, this is a test message. Please respond briefly.",
                stream = false,
                options = new
                {
                    temperature = TemperatureSlider.Value,
                    num_predict = int.Parse(MaxTokensInput.Text)
                }
            };

            var jsonRequest = JsonConvert.SerializeObject(testRequest);
            var content = new StringContent(jsonRequest, Encoding.UTF8, "application/json");
            
            var response = await httpClient.PostAsync(OllamaUrlInput.Text, content);
            
            if (response.IsSuccessStatusCode)
            {
                var jsonResponse = await response.Content.ReadAsStringAsync();
                var ollamaResponse = JsonConvert.DeserializeObject<OllamaResponse>(jsonResponse);
                
                var responseText = ollamaResponse?.Response ?? "No response";
                AiStatusText.Text = $"✅ AI connection successful! Response: {responseText.Substring(0, Math.Min(50, responseText.Length))}...";
                AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            }
            else
            {
                AiStatusText.Text = $"❌ AI connection failed: HTTP {response.StatusCode}";
                AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
            }
        }
        catch (Exception ex)
        {
            AiStatusText.Text = $"❌ AI connection failed: {ex.Message}";
            AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }
    }

    private async void ListAvailableModels(object sender, RoutedEventArgs e)
    {
        try
        {
            AiStatusText.Text = "Fetching available models...";
            AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            
            var modelsUrl = OllamaUrlInput.Text.Replace("/api/generate", "/api/tags");
            var response = await httpClient.GetAsync(modelsUrl);
            
            if (response.IsSuccessStatusCode)
            {
                var jsonResponse = await response.Content.ReadAsStringAsync();
                var modelsResponse = JsonConvert.DeserializeObject<ModelsResponse>(jsonResponse);
                
                var modelNames = string.Join(", ", modelsResponse?.Models?.Select(m => m.Name) ?? new[] { "No models found" });
                AiStatusText.Text = $"✅ Available models: {modelNames}";
                AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            }
            else
            {
                AiStatusText.Text = $"❌ Failed to fetch models: HTTP {response.StatusCode}";
                AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
            }
        }
        catch (Exception ex)
        {
            AiStatusText.Text = $"❌ Failed to fetch models: {ex.Message}";
            AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }
    }

    private void SaveAISettings(object sender, RoutedEventArgs e)
    {
        try
        {
            var config = new AIModelConfiguration
            {
                OllamaUrl = OllamaUrlInput.Text,
                ModelName = ModelNameInput.Text,
                Temperature = TemperatureSlider.Value,
                MaxTokens = int.Parse(MaxTokensInput.Text)
            };
            
            // Save to configuration file
            var configJson = JsonConvert.SerializeObject(config, Formatting.Indented);
            File.WriteAllText("ai_model_config.json", configJson);
            
            MessageBox.Show("AI model settings saved successfully!", "Settings Saved", 
                          MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Error saving AI model settings: {ex.Message}", "Save Error", 
                          MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    #endregion

    #region App Configuration

    private void SaveAppSettings(object sender, RoutedEventArgs e)
    {
        try
        {
            var config = new AppConfiguration
            {
                StatsUpdateIntervalMinutes = int.Parse(StatsUpdateIntervalInput.Text),
                AutoStartVoice = AutoStartVoiceCheckBox.IsChecked ?? false,
                MinimizeToTray = false
            };
            
            // Save to configuration file
            var configJson = JsonConvert.SerializeObject(config, Formatting.Indented);
            File.WriteAllText("app_config.json", configJson);
            
            MessageBox.Show("Application settings saved successfully! Restart the app for changes to take effect.", "Settings Saved", 
                          MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Error saving application settings: {ex.Message}", "Save Error", 
                          MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    #endregion

    #region System Information

    private void RefreshSystemInfo(object sender, RoutedEventArgs e)
    {
        UpdateSystemInfo();
        MessageBox.Show("System information refreshed!", "Information Updated", 
                      MessageBoxButton.OK, MessageBoxImage.Information);
    }

    #endregion

    #region Cleanup

    protected override void OnClosed(EventArgs e)
    {
        httpClient?.Dispose();
        base.OnClosed(e);
    }

    #endregion
}

// Configuration Models
public class AIConfiguration
{
    public string Name { get; set; } = string.Empty;
    public string Personality { get; set; } = string.Empty;
    public string SystemPrompt { get; set; } = string.Empty;
}

public class DatabaseConfiguration
{
    public string Server { get; set; } = string.Empty;
    public string Database { get; set; } = string.Empty;
    public string Username { get; set; } = string.Empty;
    public string Password { get; set; } = string.Empty;
}

public class AIModelConfiguration
{
    public string OllamaUrl { get; set; } = string.Empty;
    public string ModelName { get; set; } = string.Empty;
    public double Temperature { get; set; }
    public int MaxTokens { get; set; }
}

public class ModelsResponse
{
    public List<ModelInfo> Models { get; set; } = new List<ModelInfo>();
}

public class ModelInfo
{
    public string Name { get; set; } = string.Empty;
    public string Digest { get; set; } = string.Empty;
    public long Size { get; set; }
}

