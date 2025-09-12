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
    private MySqlConnection? aiDbConnection;
    private MySqlConnection? pulseDbConnection;
    private bool isAiDbConnected = false;
    private bool isPulseDbConnected = false;
    
    public AdminWindow()
    {
        InitializeComponent();
        httpClient = new HttpClient();
        httpClient.Timeout = TimeSpan.FromSeconds(30);
        
        // Ensure data directories exist
        EnsureDataDirectories();
        
        LoadCurrentConfiguration();
        UpdateSystemInfo();
    }
    
    private void EnsureDataDirectories()
    {
        var dataDir = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "..", "..", "..", "..", "data");
        var pwsDir = Path.Combine(dataDir, "pws");
        
        Directory.CreateDirectory(dataDir);
        Directory.CreateDirectory(pwsDir);
    }
    
    private string GetConfigPath(string filename, bool isPasswordFile = false)
    {
        var baseDir = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "..", "..", "..", "..");
        var configDir = isPasswordFile ? Path.Combine(baseDir, "data", "pws") : Path.Combine(baseDir, "data");
        return Path.Combine(configDir, filename);
    }

    private void LoadCurrentConfiguration()
    {
        // Load AI Configuration
        try
        {
            var aiConfigPath = GetConfigPath("ai_config.json");
            if (File.Exists(aiConfigPath))
            {
                var aiConfigJson = File.ReadAllText(aiConfigPath);
                var aiConfig = JsonConvert.DeserializeObject<AIConfiguration>(aiConfigJson);
                if (aiConfig != null)
                {
                    AiNameInput.Text = aiConfig.Name;
                    SystemPromptInput.Text = aiConfig.SystemPrompt;
                    // Set personality combo box
                    foreach (ComboBoxItem item in PersonalityComboBox.Items)
                    {
                        if (item.Content?.ToString() == aiConfig.Personality)
                        {
                            PersonalityComboBox.SelectedItem = item;
                            break;
                        }
                    }
                }
            }
        }
        catch { /* Ignore config load errors */ }

        // Load AI Database Configuration
        try
        {
            var aiDbConfigPath = GetConfigPath("ai_db_config.json", true);
            if (File.Exists(aiDbConfigPath))
            {
                var aiDbConfigJson = File.ReadAllText(aiDbConfigPath);
                var aiDbConfig = JsonConvert.DeserializeObject<DatabaseConfiguration>(aiDbConfigJson);
                if (aiDbConfig != null)
                {
                    AiDbServerInput.Text = aiDbConfig.Server;
                    AiDbNameInput.Text = aiDbConfig.Database;
                    AiDbUserInput.Text = aiDbConfig.Username;
                    AiDbPasswordInput.Password = aiDbConfig.Password;
                }
            }
        }
        catch { /* Ignore config load errors */ }

        // Load PulseCore Database Configuration
        try
        {
            var pulseDbConfigPath = GetConfigPath("pulse_db_config.json", true);
            if (File.Exists(pulseDbConfigPath))
            {
                var pulseDbConfigJson = File.ReadAllText(pulseDbConfigPath);
                var pulseDbConfig = JsonConvert.DeserializeObject<DatabaseConfiguration>(pulseDbConfigJson);
                if (pulseDbConfig != null)
                {
                    PulseDbServerInput.Text = pulseDbConfig.Server;
                    PulseDbNameInput.Text = pulseDbConfig.Database;
                    PulseDbUserInput.Text = pulseDbConfig.Username;
                    PulseDbPasswordInput.Password = pulseDbConfig.Password;
                }
            }
        }
        catch { /* Ignore config load errors */ }

        // Load AI Model Configuration
        try
        {
            var aiModelConfigPath = GetConfigPath("ai_model_config.json");
            if (File.Exists(aiModelConfigPath))
            {
                var aiModelConfigJson = File.ReadAllText(aiModelConfigPath);
                var aiModelConfig = JsonConvert.DeserializeObject<AIModelConfiguration>(aiModelConfigJson);
                if (aiModelConfig != null)
                {
                    OllamaUrlInput.Text = aiModelConfig.OllamaUrl;
                    ModelNameInput.Text = aiModelConfig.ModelName;
                    TemperatureSlider.Value = aiModelConfig.Temperature;
                    MaxTokensInput.Text = aiModelConfig.MaxTokens.ToString();
                }
            }
        }
        catch { /* Ignore config load errors */ }

        // Load App Configuration
        try
        {
            var appConfigPath = GetConfigPath("app_config.json");
            if (File.Exists(appConfigPath))
            {
                var appConfigJson = File.ReadAllText(appConfigPath);
                var appConfig = JsonConvert.DeserializeObject<AppConfiguration>(appConfigJson);
                if (appConfig != null)
                {
                    StatsUpdateIntervalInput.Text = appConfig.StatsUpdateIntervalMinutes.ToString();
                    AutoStartVoiceCheckBox.IsChecked = appConfig.AutoStartVoice;
                }
            }
        }
        catch { /* Ignore config load errors */ }
        
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
            var configPath = GetConfigPath("ai_config.json");
            File.WriteAllText(configPath, configJson);
            
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

    #region AI Database Configuration

    private async void TestAiDatabaseConnection(object sender, RoutedEventArgs e)
    {
        try
        {
            AiDbStatusText.Text = "Testing AI database connection...";
            AiDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            
            var connectionString = $"Server={AiDbServerInput.Text};Database={AiDbNameInput.Text};Uid={AiDbUserInput.Text};Pwd={AiDbPasswordInput.Password};";
            
            using var connection = new MySqlConnection(connectionString);
            await connection.OpenAsync();
            
            // Test AI-specific tables
            using var cmd = new MySqlCommand("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @db AND TABLE_NAME LIKE 'ai_%'", connection);
            cmd.Parameters.AddWithValue("@db", AiDbNameInput.Text);
            var aiTables = await cmd.ExecuteScalarAsync();
            
            AiDbStatusText.Text = $"✅ AI Database connection successful! Found {aiTables} AI tables";
            AiDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            
            UpdateSystemInfo();
        }
        catch (Exception ex)
        {
            AiDbStatusText.Text = $"❌ AI Database connection failed: {ex.Message}";
            AiDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }
    }

    private async void ConnectAiDatabase(object sender, RoutedEventArgs e)
    {
        try
        {
            if (isAiDbConnected && aiDbConnection != null)
            {
                aiDbConnection.Close();
                aiDbConnection.Dispose();
                isAiDbConnected = false;
                AiDbStatusText.Text = "AI Database disconnected";
                AiDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("TextMuted");
                ((Button)sender).Content = "🔌 Connect";
                return;
            }

            AiDbStatusText.Text = "Connecting to AI database...";
            AiDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            
            var connectionString = $"Server={AiDbServerInput.Text};Database={AiDbNameInput.Text};Uid={AiDbUserInput.Text};Pwd={AiDbPasswordInput.Password};";
            
            aiDbConnection = new MySqlConnection(connectionString);
            await aiDbConnection.OpenAsync();
            
            // Verify connection with a test query
            using var cmd = new MySqlCommand("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @db AND TABLE_NAME LIKE 'ai_%'", aiDbConnection);
            cmd.Parameters.AddWithValue("@db", AiDbNameInput.Text);
            var aiTables = await cmd.ExecuteScalarAsync();
            
            isAiDbConnected = true;
            AiDbStatusText.Text = $"✅ AI Database connected! Found {aiTables} AI tables. Connection is persistent.";
            AiDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            ((Button)sender).Content = "❌ Disconnect";
            
            UpdateSystemInfo();
        }
        catch (Exception ex)
        {
            AiDbStatusText.Text = $"❌ AI Database connection failed: {ex.Message}";
            AiDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
            isAiDbConnected = false;
        }
    }

    private void SaveAiDatabaseConfiguration(object sender, RoutedEventArgs e)
    {
        try
        {
            var config = new DatabaseConfiguration
            {
                Server = AiDbServerInput.Text,
                Database = AiDbNameInput.Text,
                Username = AiDbUserInput.Text,
                Password = AiDbPasswordInput.Password
            };
            
            // Save to configuration file
            var configJson = JsonConvert.SerializeObject(config, Formatting.Indented);
            var configPath = GetConfigPath("ai_db_config.json", true); // Save to /data/pws (contains password)
            File.WriteAllText(configPath, configJson);
            
            MessageBox.Show("AI Database configuration saved successfully!", "Configuration Saved", 
                          MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Error saving AI database configuration: {ex.Message}", "Save Error", 
                          MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    #endregion

    #region PulseCore Database Configuration

    private async void TestPulseDatabaseConnection(object sender, RoutedEventArgs e)
    {
        try
        {
            PulseDbStatusText.Text = "Testing PulseCore database connection...";
            PulseDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            
            var connectionString = $"Server={PulseDbServerInput.Text};Database={PulseDbNameInput.Text};Uid={PulseDbUserInput.Text};Pwd={PulseDbPasswordInput.Password};";
            
            using var connection = new MySqlConnection(connectionString);
            await connection.OpenAsync();
            
            // Test PulseCore-specific tables
            using var cmd = new MySqlCommand("SELECT COUNT(*) FROM nova_events", connection);
            var novaCount = await cmd.ExecuteScalarAsync();
            
            using var cmd2 = new MySqlCommand("SELECT COUNT(*) FROM climax_groups", connection);
            var climaxCount = await cmd2.ExecuteScalarAsync();
            
            PulseDbStatusText.Text = $"✅ PulseCore Database connection successful! {novaCount} novas, {climaxCount} climax groups";
            PulseDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            
            UpdateSystemInfo();
        }
        catch (Exception ex)
        {
            PulseDbStatusText.Text = $"❌ PulseCore Database connection failed: {ex.Message}";
            PulseDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }
    }

    private async void ConnectPulseDatabase(object sender, RoutedEventArgs e)
    {
        try
        {
            if (isPulseDbConnected && pulseDbConnection != null)
            {
                pulseDbConnection.Close();
                pulseDbConnection.Dispose();
                isPulseDbConnected = false;
                PulseDbStatusText.Text = "PulseCore Database disconnected";
                PulseDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("TextMuted");
                ((Button)sender).Content = "🔌 Connect";
                return;
            }

            PulseDbStatusText.Text = "Connecting to PulseCore database...";
            PulseDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            
            var connectionString = $"Server={PulseDbServerInput.Text};Database={PulseDbNameInput.Text};Uid={PulseDbUserInput.Text};Pwd={PulseDbPasswordInput.Password};";
            
            pulseDbConnection = new MySqlConnection(connectionString);
            await pulseDbConnection.OpenAsync();
            
            // Verify connection with PulseCore data
            using var cmd = new MySqlCommand("SELECT COUNT(*) FROM nova_events", pulseDbConnection);
            var novaCount = await cmd.ExecuteScalarAsync();
            
            using var cmd2 = new MySqlCommand("SELECT COUNT(*) FROM climax_groups", pulseDbConnection);
            var climaxCount = await cmd2.ExecuteScalarAsync();
            
            isPulseDbConnected = true;
            PulseDbStatusText.Text = $"✅ PulseCore Database connected! {novaCount} novas, {climaxCount} climax groups. Connection is persistent.";
            PulseDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            ((Button)sender).Content = "❌ Disconnect";
            
            UpdateSystemInfo();
        }
        catch (Exception ex)
        {
            PulseDbStatusText.Text = $"❌ PulseCore Database connection failed: {ex.Message}";
            PulseDbStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
            isPulseDbConnected = false;
        }
    }

    private void SavePulseDatabaseConfiguration(object sender, RoutedEventArgs e)
    {
        try
        {
            var config = new DatabaseConfiguration
            {
                Server = PulseDbServerInput.Text,
                Database = PulseDbNameInput.Text,
                Username = PulseDbUserInput.Text,
                Password = PulseDbPasswordInput.Password
            };
            
            // Save to configuration file
            var configJson = JsonConvert.SerializeObject(config, Formatting.Indented);
            var configPath = GetConfigPath("pulse_db_config.json", true); // Save to /data/pws (contains password)
            File.WriteAllText(configPath, configJson);
            
            MessageBox.Show("PulseCore Database configuration saved successfully!", "Configuration Saved", 
                          MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Error saving PulseCore database configuration: {ex.Message}", "Save Error", 
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
            var configPath = GetConfigPath("ai_model_config.json");
            File.WriteAllText(configPath, configJson);
            
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
            var configPath = GetConfigPath("app_config.json");
            File.WriteAllText(configPath, configJson);
            
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
        aiDbConnection?.Dispose();
        pulseDbConnection?.Dispose();
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

public class AppConfiguration
{
    public int StatsUpdateIntervalMinutes { get; set; } = 5;
    public bool AutoStartVoice { get; set; } = false;
    public bool MinimizeToTray { get; set; } = false;
}

