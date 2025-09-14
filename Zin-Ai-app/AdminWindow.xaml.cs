using System.Windows;
using System.Windows.Controls;
using System.Linq;
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
        LoadCurrentSSLConfiguration();
        UpdateSystemInfo();
        
        // Initialize bridge status displays
        InitializeBridgeStatusDisplays();
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

    private void InitializeBridgeStatusDisplays()
    {
        // Initialize all bridge status displays with current centralized status
        if (BridgeStatusText != null)
        {
            BridgeStatusText.Text = BridgeStatus;
            BridgeStatusText.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
        }

        if (ActivityBridgeSSLStatus != null)
        {
            ActivityBridgeSSLStatus.Text = BridgeStatus;
            ActivityBridgeSSLStatus.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
        }

        if (ActivityBridgeAPIStatus != null)
        {
            ActivityBridgeAPIStatus.Text = BridgeStatus;
            ActivityBridgeAPIStatus.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
        }
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

        // Load Bridge Configuration
        try
        {
            var bridgeConfigPath = GetConfigPath("bridge_config.json", true);
            if (File.Exists(bridgeConfigPath))
            {
                var bridgeConfigJson = File.ReadAllText(bridgeConfigPath);
                var bridgeConfig = JsonConvert.DeserializeObject<BridgeConfiguration>(bridgeConfigJson);
                if (bridgeConfig != null)
                {
                    BridgeHostInput.Text = bridgeConfig.Host;
                    BridgePortInput.Text = bridgeConfig.Port.ToString();
                    BridgeApiKeyInput.Password = bridgeConfig.ApiKey;
                    // Set connection type combo box
                    foreach (ComboBoxItem item in BridgeTypeComboBox.Items)
                    {
                        if (item.Content?.ToString() == bridgeConfig.ConnectionType)
                        {
                            BridgeTypeComboBox.SelectedItem = item;
                            break;
                        }
                    }
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

    private void SaveGeneralConfiguration(object sender, RoutedEventArgs e)
    {
        try
        {
            var updateInterval = 5; // Default
            if (int.TryParse(UpdateIntervalInput.Text, out var parsedInterval) && parsedInterval > 0)
            {
                updateInterval = parsedInterval;
            }

            var config = new GeneralConfiguration
            {
                UpdateIntervalSeconds = updateInterval,
                AutoRefreshEnabled = AutoRefreshCheckBox.IsChecked ?? true
            };

            // Save to configuration file
            var configJson = JsonConvert.SerializeObject(config, Formatting.Indented);
            var configPath = GetConfigPath("general_config.json", false); // General config doesn't need password protection
            File.WriteAllText(configPath, configJson);

            GeneralConfigStatusText.Text = "✅ General configuration saved successfully!";
            GeneralConfigStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");

            // Apply settings immediately - update MainWindow UI refresh timer
            MainWindow.UpdateGeneralConfiguration(config.UpdateIntervalSeconds, config.AutoRefreshEnabled);
        }
        catch (Exception ex)
        {
            GeneralConfigStatusText.Text = $"❌ Error saving configuration: {ex.Message}";
            GeneralConfigStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }
    }

    #endregion

    #region Bridge Connection Configuration

    private async void TestBridgeConnection(object sender, RoutedEventArgs e)
    {
        await TestBridgeConnection();
        
        // Update local display from centralized status
        BridgeStatusText.Text = BridgeStatus;
        BridgeStatusText.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
    }

    private async void ConnectBridge(object sender, RoutedEventArgs e)
    {
        try
        {
            var button = (Button)sender;
            var currentContent = button.Content?.ToString();
            
            // Check if we're disconnecting
            if (!string.IsNullOrEmpty(currentContent) && currentContent.Contains("Disconnect"))
            {
                // Handle Disconnect Bridge
                UpdateBridgeStatus("● Disconnected", "AccentOrange");
                BridgeStatusText.Text = BridgeStatus;
                BridgeStatusText.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
                button.Content = "🔌 Connect Bridge";
                return;
            }
            
            // Handle Connect Bridge - use unified connection testing
            await TestBridgeConnection();
            
            // Update local display and button based on result
            BridgeStatusText.Text = BridgeStatus;
            BridgeStatusText.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
            
            if (BridgeStatus.Contains("Connected"))
            {
                button.Content = "❌ Disconnect Bridge";
            }
        }
        catch (Exception ex)
        {
            UpdateBridgeStatus("● Not Available", "AccentPink");
            BridgeStatusText.Text = BridgeStatus;
            BridgeStatusText.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
        }
    }

    private void SaveBridgeConfiguration(object sender, RoutedEventArgs e)
    {
        try
        {
            var config = new BridgeConfiguration
            {
                Host = BridgeHostInput.Text,
                Port = int.Parse(BridgePortInput.Text),
                ApiKey = BridgeApiKeyInput.Password,
                ConnectionType = ((ComboBoxItem)BridgeTypeComboBox.SelectedItem)?.Content?.ToString() ?? "HTTP"
            };
            
            // Save to configuration file
            var configJson = JsonConvert.SerializeObject(config, Formatting.Indented);
            var configPath = GetConfigPath("bridge_config.json", true); // Save to /data/pws (contains API key)
            File.WriteAllText(configPath, configJson);
            
            MessageBox.Show("Bridge configuration saved successfully!", "Configuration Saved", 
                          MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Error saving bridge configuration: {ex.Message}", "Save Error", 
                          MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    #endregion

    #region SSL Certificate Configuration

    private void LoadCurrentSSLConfiguration()
    {
        try
        {
            // Load SSL configuration from file
            var configPath = GetConfigPath("ssl_config.json", true);
            if (File.Exists(configPath))
            {
                var configJson = File.ReadAllText(configPath);
                var sslConfig = JsonConvert.DeserializeObject<dynamic>(configJson);
                
                // Update UI elements with loaded configuration
                if (sslConfig != null)
                {
                    // Only update SSL panel visibility based on current bridge type selection
                    // DO NOT override bridge configuration connection type
                    if (BridgeTypeComboBox != null && SSLConfigurationPanel != null)
                    {
                        var currentConnectionType = ((ComboBoxItem)BridgeTypeComboBox.SelectedItem)?.Content?.ToString() ?? "HTTP";
                        SSLConfigurationPanel.Visibility = currentConnectionType == "HTTPS" ? Visibility.Visible : Visibility.Collapsed;
                    }
                    SSLPortInput.Text = sslConfig.port?.ToString() ?? "8443";
                    
                    // Update status display
                    UpdateSSLStatus(sslConfig);
                    
                    SSLStatusText.Text = "✅ SSL configuration loaded";
                    SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
                }
            }
            else
            {
                // No SSL config found, show default status
                SSLStatusText.Text = "No SSL configuration found";
                SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("TextMuted");
            }
            
            // Check if certificate files exist
            CheckSSLCertificateFiles();
        }
        catch (Exception ex)
        {
            SSLStatusText.Text = $"⚠️ Error loading SSL configuration: {ex.Message}";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentOrange");
        }
    }

    private void UpdateSSLStatus(dynamic sslConfig)
    {
        // Update status based on configuration
        bool enabled = sslConfig?.enabled ?? false;
        
        // DO NOT update connection type ComboBox - respect bridge configuration
        // The bridge configuration should determine the connection type, not SSL config
        
        if (enabled)
        {
            SSLStatusText.Text = $"✅ HTTPS enabled on port {sslConfig?.port ?? 8443}";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
        }
        else
        {
            SSLStatusText.Text = "HTTPS disabled";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("TextMuted");
        }
    }

    private void CheckSSLCertificateFiles()
    {
        try
        {
            var certsDir = GetConfigPath("certs", true);
            var certFile = Path.Combine(certsDir, "server.crt");
            var keyFile = Path.Combine(certsDir, "server.key");
            
            bool certExists = File.Exists(certFile);
            bool keyExists = File.Exists(keyFile);
            
            if (certExists && keyExists)
            {
                // Show certificate file information in the path inputs (read-only)
                SSLCertPathInput.Text = "server.crt (uploaded)";
                SSLKeyPathInput.Text = "server.key (uploaded)";
                
                // Update status to show certificates are available
                if (SSLStatusText.Text.Contains("configuration loaded"))
                {
                    SSLStatusText.Text = "✅ SSL configured with certificates uploaded";
                }
            }
            else if (certExists || keyExists)
            {
                SSLCertPathInput.Text = certExists ? "server.crt (uploaded)" : "";
                SSLKeyPathInput.Text = keyExists ? "server.key (uploaded)" : "";
                
                SSLStatusText.Text = "⚠️ Incomplete certificate setup - missing " + 
                                   (certExists ? "private key" : "certificate");
                SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentOrange");
            }
            else
            {
                SSLCertPathInput.Text = "";
                SSLKeyPathInput.Text = "";
            }
        }
        catch (Exception ex)
        {
            SSLStatusText.Text = $"⚠️ Error checking certificate files: {ex.Message}";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentOrange");
        }
    }

    private void BrowseSSLCert(object sender, RoutedEventArgs e)
    {
        var openFileDialog = new Microsoft.Win32.OpenFileDialog
        {
            Title = "Select SSL Certificate File",
            Filter = "Certificate files (*.crt;*.pem;*.cert)|*.crt;*.pem;*.cert|All files (*.*)|*.*",
            CheckFileExists = true
        };

        if (openFileDialog.ShowDialog() == true)
        {
            SSLCertPathInput.Text = openFileDialog.FileName;
        }
    }

    private void BrowseSSLKey(object sender, RoutedEventArgs e)
    {
        var openFileDialog = new Microsoft.Win32.OpenFileDialog
        {
            Title = "Select SSL Private Key File",
            Filter = "Key files (*.key;*.pem)|*.key;*.pem|All files (*.*)|*.*",
            CheckFileExists = true
        };

        if (openFileDialog.ShowDialog() == true)
        {
            SSLKeyPathInput.Text = openFileDialog.FileName;
        }
    }

    private void UploadSSLCertificates(object sender, RoutedEventArgs e)
    {
        try
        {
            if (string.IsNullOrEmpty(SSLCertPathInput.Text) || string.IsNullOrEmpty(SSLKeyPathInput.Text))
            {
                SSLStatusText.Text = "❌ Please select both certificate and key files";
                SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
                return;
            }

            SSLStatusText.Text = "Uploading SSL certificates...";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");

            // Create target directory
            var certsDir = GetConfigPath("certs", true);
            if (!Directory.Exists(certsDir))
            {
                Directory.CreateDirectory(certsDir);
            }

            // Copy certificate files
            var certDestination = Path.Combine(certsDir, "server.crt");
            var keyDestination = Path.Combine(certsDir, "server.key");

            File.Copy(SSLCertPathInput.Text, certDestination, true);
            File.Copy(SSLKeyPathInput.Text, keyDestination, true);

            SSLStatusText.Text = "✅ SSL certificates uploaded successfully!";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");

        }
        catch (Exception ex)
        {
            SSLStatusText.Text = $"❌ Failed to upload certificates: {ex.Message}";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }
    }

    private void SaveSSLConfiguration(object sender, RoutedEventArgs e)
    {
        try
        {
            // Get enabled status from connection type
            bool enabled = false;
            if (BridgeTypeComboBox.SelectedItem is ComboBoxItem selectedItem)
            {
                enabled = selectedItem.Content.ToString() == "HTTPS";
            }
            
            var sslConfig = new
            {
                enabled = enabled,
                port = int.TryParse(SSLPortInput.Text, out int port) ? port : 8443,
                cert = "server.crt",
                key = "server.key",
                updated_at = DateTime.Now.ToString("o"),
                updated_by = "desktop_admin"
            };

            var configPath = GetConfigPath("ssl_config.json", true);
            var configJson = JsonConvert.SerializeObject(sslConfig, Formatting.Indented);
            File.WriteAllText(configPath, configJson);

            SSLStatusText.Text = "✅ SSL configuration saved successfully!";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");

            MessageBox.Show("SSL configuration saved! Restart the bridge server to apply changes.",
                          "Configuration Saved", MessageBoxButton.OK, MessageBoxImage.Information);

        }
        catch (Exception ex)
        {
            SSLStatusText.Text = $"❌ Failed to save SSL configuration: {ex.Message}";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }
    }

    private async void TestHTTPSConnection(object sender, RoutedEventArgs e)
    {
        try
        {
            SSLStatusText.Text = "Testing HTTPS connection...";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");

            var port = int.TryParse(SSLPortInput.Text, out int sslPort) ? sslPort : 8443;
            var httpsUrl = $"https://pulsecore.one:{port}/ai/api/pc-bridge-status.php";

            using var request = new HttpRequestMessage(HttpMethod.Get, httpsUrl);
            
            // For self-signed certificates, we might need to bypass SSL validation
            var handler = new HttpClientHandler()
            {
                ServerCertificateCustomValidationCallback = (message, cert, chain, sslPolicyErrors) => true
            };
            
            using var httpsClient = new HttpClient(handler);
            var response = await httpsClient.SendAsync(request);

            if (response.IsSuccessStatusCode)
            {
                SSLStatusText.Text = "✅ HTTPS connection successful!";
                SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            }
            else
            {
                SSLStatusText.Text = $"❌ HTTPS connection failed: HTTP {response.StatusCode}";
                SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
            }

        }
        catch (Exception ex)
        {
            SSLStatusText.Text = $"❌ HTTPS test failed: {ex.Message}";
            SSLStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
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
        AiStatusText.Text = "Testing AI connection...";
        AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");

        var (success, message) = await TestOllamaConnection(
            ModelNameInput.Text,
            TemperatureSlider.Value,
            int.Parse(MaxTokensInput.Text)
        );

        AiStatusText.Text = success ? $"✅ {message}" : $"❌ {message}";
        AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource(success ? "AccentGreen" : "AccentPink");
    }

    private async void ListAvailableModels(object sender, RoutedEventArgs e)
    {
        AiStatusText.Text = "Fetching available models...";
        AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");

        var (success, message) = await GetAvailableModels();

        AiStatusText.Text = success ? $"✅ {message}" : $"❌ {message}";
        AiStatusText.Foreground = (System.Windows.Media.Brush)FindResource(success ? "AccentGreen" : "AccentPink");
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
    
    #region Tab Navigation

    private void ShowActivityTab(object sender, RoutedEventArgs e)
    {
        SetActiveTab("Activity");
    }

    private void ShowConfigurationTab(object sender, RoutedEventArgs e)
    {
        SetActiveTab("Configuration");
    }

    private void ShowIdentityTab(object sender, RoutedEventArgs e)
    {
        SetActiveTab("Identity");
    }

    private void SetActiveTab(string tabName)
    {
        // Hide all tab contents
        ActivityTabContent.Visibility = Visibility.Collapsed;
        ConfigurationTabContent.Visibility = Visibility.Collapsed;
        IdentityTabContent.Visibility = Visibility.Collapsed;

        // Reset all tab button styles
        ActivityTabBtn.Style = (Style)FindResource("AdminTabButtonStyle");
        ConfigurationTabBtn.Style = (Style)FindResource("AdminTabButtonStyle");
        IdentityTabBtn.Style = (Style)FindResource("AdminTabButtonStyle");

        // Show selected tab and set active style
        switch (tabName)
        {
            case "Activity":
                ActivityTabContent.Visibility = Visibility.Visible;
                ActivityTabBtn.Style = (Style)FindResource("AdminTabButtonActiveStyle");
                break;
            case "Configuration":
                ConfigurationTabContent.Visibility = Visibility.Visible;
                ConfigurationTabBtn.Style = (Style)FindResource("AdminTabButtonActiveStyle");
                break;
            case "Identity":
                IdentityTabContent.Visibility = Visibility.Visible;
                IdentityTabBtn.Style = (Style)FindResource("AdminTabButtonActiveStyle");
                break;
        }
    }

    #endregion

    #region Activity Tab Functions

    private void RefreshActivityStats(object sender, RoutedEventArgs e)
    {
        LoadActivityStatistics();
    }

    private async void TestAllConnections(object sender, RoutedEventArgs e)
    {
        // Test bridge connection using unified method
        await TestBridgeConnection();
        
        // Update Activity tab displays with centralized status
        ActivityBridgeSSLStatus.Text = BridgeStatus;
        ActivityBridgeSSLStatus.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
        
        ActivityBridgeAPIStatus.Text = BridgeStatus;
        ActivityBridgeAPIStatus.Foreground = (System.Windows.Media.Brush)FindResource(BridgeStatusColorName);
        
        // Test other connections (PulseDB, AI DB, etc.)
        TestActivityConnections();
    }

    private async void LoadActivityStatistics()
    {
        try
        {
            // Test connections without await since these are void methods
            TestPulseDatabaseConnection(null!, null!);
            
            // Test AI database connection  
            TestAiDatabaseConnection(null!, null!);
            
            // Get statistics for activity display
            var pulseConfigPath = GetConfigPath("pulse_db_config.json", true);
            if (File.Exists(pulseConfigPath))
            {
                var pulseConfigJson = File.ReadAllText(pulseConfigPath);
                var pulseConfig = JsonConvert.DeserializeObject<DatabaseConfiguration>(pulseConfigJson);
                if (pulseConfig != null)
                {
                    var connectionString = $"Server={pulseConfig.Server};Database={pulseConfig.Database};Uid={pulseConfig.Username};Pwd={pulseConfig.Password};";
                    
                    using var connection = new MySqlConnection(connectionString);
                    await connection.OpenAsync();
                    
                    // Get total novas
                    using (var cmd = new MySqlCommand("SELECT COUNT(*) FROM nova_events", connection))
                    {
                        var result = await cmd.ExecuteScalarAsync();
                        ActivityTotalNovas.Text = result?.ToString() ?? "0";
                    }
                    
                    // Get total climaxes
                    using (var cmd = new MySqlCommand("SELECT COUNT(*) FROM climax_groups", connection))
                    {
                        var result = await cmd.ExecuteScalarAsync();
                        ActivityTotalClimaxes.Text = result?.ToString() ?? "0";
                    }
                    
                    // Get average complexity
                    using (var cmd = new MySqlCommand("SELECT AVG(complexity) FROM nova_events WHERE complexity IS NOT NULL", connection))
                    {
                        var result = await cmd.ExecuteScalarAsync();
                        if (result != DBNull.Value && result != null)
                        {
                            ActivityAvgComplexity.Text = $"{Convert.ToDouble(result):F2}";
                        }
                        else
                        {
                            ActivityAvgComplexity.Text = "0.00";
                        }
                    }
                    
                    ActivityConnectionStatus.Text = "Connected";
                    ActivityConnectionStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
                    ActivityLastUpdate.Text = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
                }
            }
        }
        catch (Exception)
        {
            ActivityConnectionStatus.Text = "Error";
            ActivityConnectionStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
            ActivityTotalNovas.Text = "Error";
            ActivityTotalClimaxes.Text = "Error";
            ActivityAvgComplexity.Text = "Error";
        }
    }

    private async void TestActivityConnections()
    {
        // Test PulseCore Database
        try
        {
            var pulseConfigPath = GetConfigPath("pulse_db_config.json", true);
            if (File.Exists(pulseConfigPath))
            {
                var pulseConfigJson = File.ReadAllText(pulseConfigPath);
                var pulseConfig = JsonConvert.DeserializeObject<DatabaseConfiguration>(pulseConfigJson);
                if (pulseConfig != null)
                {
                    var connectionString = $"Server={pulseConfig.Server};Database={pulseConfig.Database};Uid={pulseConfig.Username};Pwd={pulseConfig.Password};";
                    
                    using var connection = new MySqlConnection(connectionString);
                    await connection.OpenAsync();
                    
                    ActivityPulseDbStatus.Text = "● Connected";
                    ActivityPulseDbStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
                }
            }
            else
            {
                ActivityPulseDbStatus.Text = "● Not Configured";
                ActivityPulseDbStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            }
        }
        catch (Exception)
        {
            ActivityPulseDbStatus.Text = "● Connection Failed";
            ActivityPulseDbStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }

        // Test AI Database
        try
        {
            var aiConfigPath = GetConfigPath("ai_db_config.json", true);
            if (File.Exists(aiConfigPath))
            {
                var aiConfigJson = File.ReadAllText(aiConfigPath);
                var aiConfig = JsonConvert.DeserializeObject<DatabaseConfiguration>(aiConfigJson);
                if (aiConfig != null)
                {
                    var connectionString = $"Server={aiConfig.Server};Database={aiConfig.Database};Uid={aiConfig.Username};Pwd={aiConfig.Password};";
                    
                    using var connection = new MySqlConnection(connectionString);
                    await connection.OpenAsync();
                    
                    ActivityAiDbStatus.Text = "● Connected";
                    ActivityAiDbStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
                }
            }
            else
            {
                ActivityAiDbStatus.Text = "● Not Configured";
                ActivityAiDbStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentCyan");
            }
        }
        catch (Exception)
        {
            ActivityAiDbStatus.Text = "● Connection Failed";
            ActivityAiDbStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }

        // Test Ollama AI
        try
        {
            var testRequest = new
            {
                model = "llama3.2:3b-instruct-q4_K_M",
                prompt = "Test",
                stream = false
            };

            var jsonRequest = JsonConvert.SerializeObject(testRequest);
            var content = new StringContent(jsonRequest, Encoding.UTF8, "application/json");
            
            var response = await httpClient.PostAsync("http://localhost:11434/api/generate", content);
            
            if (response.IsSuccessStatusCode)
            {
                ActivityOllamaStatus.Text = "● Connected";
                ActivityOllamaStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentGreen");
            }
            else
            {
                ActivityOllamaStatus.Text = "● Server Error";
                ActivityOllamaStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
            }
        }
        catch (Exception)
        {
            ActivityOllamaStatus.Text = "● Not Available";
            ActivityOllamaStatus.Foreground = (System.Windows.Media.Brush)FindResource("AccentPink");
        }

        // Test Bridge connections - REMOVED (Now using unified bridge status manager)
    }

    #endregion

    #region Unified Bridge Status - Simple Solution

    // Single source of truth for all bridge status displays
    public static string BridgeStatus { get; private set; } = "● Not Configured";
    public static string BridgeStatusColorName { get; private set; } = "AccentCyan";

    public static void UpdateBridgeStatus(string text, string colorName)
    {
        BridgeStatus = text;
        BridgeStatusColorName = colorName;
    }

    public static async Task TestBridgeConnection()
    {
        try
        {
            UpdateBridgeStatus("● Connecting...", "AccentCyan");

            var bridgeConfigPath = GetConfigPathStatic("bridge_config.json", true);
            if (!File.Exists(bridgeConfigPath))
            {
                UpdateBridgeStatus("● Not Configured", "AccentCyan");
                return;
            }

            var bridgeConfigJson = File.ReadAllText(bridgeConfigPath);
            var bridgeConfig = JsonConvert.DeserializeObject<BridgeConfiguration>(bridgeConfigJson);

            if (bridgeConfig == null)
            {
                UpdateBridgeStatus("● Not Configured", "AccentCyan");
                return;
            }

            var protocol = bridgeConfig.ConnectionType?.ToLower() == "https" ? "https" : "http";
            var apiUrl = $"{protocol}://{bridgeConfig.Host}:{bridgeConfig.Port}/ai/api/pc-bridge-status.php";

            using var httpClient = new HttpClient();
            using var request = new HttpRequestMessage(HttpMethod.Get, apiUrl);

            if (!string.IsNullOrEmpty(bridgeConfig.ApiKey))
            {
                request.Headers.Authorization = new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", bridgeConfig.ApiKey);
            }

            httpClient.Timeout = TimeSpan.FromSeconds(5);
            using var response = await httpClient.SendAsync(request);

            if (response.IsSuccessStatusCode)
            {
                UpdateBridgeStatus("● Connected", "AccentGreen");
            }
            else if (response.StatusCode == System.Net.HttpStatusCode.Unauthorized)
            {
                UpdateBridgeStatus("● Auth Error", "AccentOrange");
            }
            else
            {
                UpdateBridgeStatus("● Server Error", "AccentPink");
            }
        }
        catch
        {
            UpdateBridgeStatus("● Not Available", "AccentPink");
        }
    }

    // Helper method for static access to config path
    private static string GetConfigPathStatic(string filename, bool isPasswordFile = false)
    {
        var baseDir = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "..", "..", "..", "..");
        var configDir = isPasswordFile ? Path.Combine(baseDir, "data", "pws") : Path.Combine(baseDir, "data");
        return Path.Combine(configDir, filename);
    }

    // Direct Ollama connection using configurable settings
    public static async Task<(bool success, string message)> TestOllamaConnection(string model, double temperature, int maxTokens)
    {
        try
        {
            // Read Ollama configuration from AI model config (user configurable)
            var aiConfigPath = GetConfigPathStatic("ai_model_config.json", true);
            string ollamaUrl = "http://127.0.0.1:11434"; // Default fallback
            
            if (File.Exists(aiConfigPath))
            {
                try
                {
                    var aiConfigJson = File.ReadAllText(aiConfigPath);
                    var aiConfig = JsonConvert.DeserializeObject<AIModelConfiguration>(aiConfigJson);
                    if (aiConfig != null && !string.IsNullOrEmpty(aiConfig.OllamaUrl))
                    {
                        ollamaUrl = aiConfig.OllamaUrl;
                    }
                }
                catch
                {
                    // Use default if config parsing fails
                }
            }

            // Ensure URL format is correct
            if (!ollamaUrl.StartsWith("http://") && !ollamaUrl.StartsWith("https://"))
            {
                ollamaUrl = "http://" + ollamaUrl;
            }
            
            var generateUrl = $"{ollamaUrl}/api/generate";

            var testRequest = new
            {
                model = model,
                prompt = "Hello, this is a test message. Please respond briefly.",
                stream = false,
                options = new
                {
                    temperature = temperature,
                    num_predict = maxTokens
                }
            };

            using var httpClient = new HttpClient();
            var jsonRequest = JsonConvert.SerializeObject(testRequest);
            var content = new StringContent(jsonRequest, Encoding.UTF8, "application/json");
            
            httpClient.Timeout = TimeSpan.FromSeconds(30);
            var response = await httpClient.PostAsync(generateUrl, content);

            if (response.IsSuccessStatusCode)
            {
                var jsonResponse = await response.Content.ReadAsStringAsync();
                var ollamaResponse = JsonConvert.DeserializeObject<OllamaResponse>(jsonResponse);
                var responseText = ollamaResponse?.Response ?? "No response";
                return (true, $"AI connection successful! Response: {responseText.Substring(0, Math.Min(50, responseText.Length))}...");
            }
            else
            {
                return (false, $"AI connection failed: HTTP {response.StatusCode}");
            }
        }
        catch (Exception ex)
        {
            return (false, $"AI connection failed: {ex.Message}");
        }
    }

    public static async Task<(bool success, string message)> GetAvailableModels()
    {
        try
        {
            // Read Ollama configuration from AI model config (user configurable)
            var aiConfigPath = GetConfigPathStatic("ai_model_config.json", true);
            string ollamaUrl = "http://127.0.0.1:11434"; // Default fallback
            
            if (File.Exists(aiConfigPath))
            {
                try
                {
                    var aiConfigJson = File.ReadAllText(aiConfigPath);
                    var aiConfig = JsonConvert.DeserializeObject<AIModelConfiguration>(aiConfigJson);
                    if (aiConfig != null && !string.IsNullOrEmpty(aiConfig.OllamaUrl))
                    {
                        ollamaUrl = aiConfig.OllamaUrl;
                    }
                }
                catch
                {
                    // Use default if config parsing fails
                }
            }

            // Ensure URL format is correct
            if (!ollamaUrl.StartsWith("http://") && !ollamaUrl.StartsWith("https://"))
            {
                ollamaUrl = "http://" + ollamaUrl;
            }
            
            var tagsUrl = $"{ollamaUrl}/api/tags";

            using var httpClient = new HttpClient();
            httpClient.Timeout = TimeSpan.FromSeconds(10);
            var response = await httpClient.GetAsync(tagsUrl);

            if (response.IsSuccessStatusCode)
            {
                var jsonResponse = await response.Content.ReadAsStringAsync();
                var modelsResponse = JsonConvert.DeserializeObject<ModelsResponse>(jsonResponse);
                var modelNames = string.Join(", ", modelsResponse?.Models?.Select(m => m.Name) ?? new[] { "No models found" });
                return (true, $"Available models: {modelNames}");
            }
            else
            {
                return (false, $"Failed to fetch models: HTTP {response.StatusCode}");
            }
        }
        catch (Exception ex)
        {
            return (false, $"Failed to fetch models: {ex.Message}");
        }
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

    private void BridgeTypeComboBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (sender is ComboBox comboBox && comboBox.SelectedItem is ComboBoxItem selectedItem)
        {
            string connectionType = selectedItem.Content?.ToString() ?? "HTTP";
            
            // Show/hide SSL configuration panel based on connection type
            if (SSLConfigurationPanel != null)
            {
                if (connectionType == "HTTPS")
                {
                    SSLConfigurationPanel.Visibility = Visibility.Visible;
                    // Update port to HTTPS default if currently on HTTP default
                    if (SSLPortInput?.Text == "8081" || SSLPortInput?.Text == "80")
                    {
                        SSLPortInput.Text = "8443";
                    }
                }
                else
                {
                    SSLConfigurationPanel.Visibility = Visibility.Collapsed;
                    // Update port to HTTP default if currently on HTTPS default
                    if (connectionType == "HTTP" && (SSLPortInput?.Text == "8443" || SSLPortInput?.Text == "443"))
                    {
                        SSLPortInput.Text = "8081";
                    }
                }
            }
        }
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

public class GeneralConfiguration
{
    public int UpdateIntervalSeconds { get; set; } = 5;
    public bool AutoRefreshEnabled { get; set; } = true;
}

public class BridgeConfiguration
{
    public string Host { get; set; } = string.Empty;
    public int Port { get; set; } = 8081;
    public string ApiKey { get; set; } = string.Empty;
    public string ConnectionType { get; set; } = "HTTP";
}

public class OllamaResponse
{
    public string Response { get; set; } = string.Empty;
    public bool Done { get; set; }
}

