using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Reflection;
using System.Threading;
using System.Windows.Forms;

[assembly: AssemblyTitle("MIKHMON PRO ADMIN Lanceur")]
[assembly: AssemblyDescription("Lanceur PHP 8 pour MIKHMON PRO ADMIN")]
[assembly: AssemblyCompany("TIKRAS IT")]
[assembly: AssemblyProduct("MIKHMON PRO ADMIN")]
[assembly: AssemblyCopyright("TIKRAS IT")]
[assembly: AssemblyVersion("3.2.0.0")]
[assembly: AssemblyFileVersion("3.2.0.0")]

namespace TikrasMikhmonLauncher
{
    internal static class Program
    {
        [STAThread]
        private static void Main()
        {
            bool created;
            using (Mutex mutex = new Mutex(true, "TikrasMikhmonProAdminLauncher", out created))
            {
                if (!created)
                {
                    MessageBox.Show("Le lanceur Mikhmon est deja ouvert.", "MIKHMON PRO ADMIN", MessageBoxButtons.OK, MessageBoxIcon.Information);
                    return;
                }

                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new LauncherForm());
            }
        }
    }

    public class LauncherForm : Form
    {
        private const string Host = "127.0.0.1";
        private const int PreferredPort = 8081;
        private const int LastPort = 8090;

        private readonly string appRoot;
        private readonly string phpExe;
        private readonly string phpIni;
        private readonly string docRoot;
        private readonly string logPath;

        private Process serverProcess;
        private bool ownsServer;
        private int activePort;

        private Label statusLabel;
        private TextBox urlBox;
        private TextBox logBox;
        private Button openButton;
        private Button restartButton;
        private Button stopButton;
        private Button logButton;
        private NotifyIcon trayIcon;

        public LauncherForm()
        {
            appRoot = AppDomain.CurrentDomain.BaseDirectory.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
            phpExe = Path.Combine(appRoot, "php8", "php.exe");
            phpIni = Path.Combine(appRoot, "php8", "php.ini");
            docRoot = Path.Combine(appRoot, "mikhmon");
            logPath = ResolveLogPath();

            InitializeWindow();
            InitializeTray();

            Load += delegate { ThreadPool.QueueUserWorkItem(delegate { StartWorkflow(true); }); };
            FormClosing += delegate { StopOwnedServer(); };
            Resize += delegate
            {
                if (WindowState == FormWindowState.Minimized)
                {
                    Hide();
                    trayIcon.Visible = true;
                }
            };
        }

        private void InitializeWindow()
        {
            Text = "MIKHMON PRO ADMIN - Lanceur";
            StartPosition = FormStartPosition.CenterScreen;
            Width = 620;
            Height = 390;
            MinimumSize = new Size(560, 340);

            try
            {
                string oldExe = Path.Combine(appRoot, "MikhmonServer.exe");
                if (File.Exists(oldExe))
                {
                    Icon oldIcon = Icon.ExtractAssociatedIcon(oldExe);
                    if (oldIcon != null)
                    {
                        Icon = oldIcon;
                    }
                }
            }
            catch
            {
            }

            Font = new Font("Segoe UI", 9F);

            Panel root = new Panel();
            root.Dock = DockStyle.Fill;
            root.Padding = new Padding(16);
            Controls.Add(root);

            Label title = new Label();
            title.Text = "MIKHMON PRO ADMIN";
            title.Font = new Font("Segoe UI", 16F, FontStyle.Bold);
            title.AutoSize = true;
            title.Left = 16;
            title.Top = 14;
            root.Controls.Add(title);

            statusLabel = new Label();
            statusLabel.Text = "Preparation du serveur...";
            statusLabel.AutoSize = false;
            statusLabel.Left = 18;
            statusLabel.Top = 58;
            statusLabel.Width = 560;
            statusLabel.Height = 24;
            root.Controls.Add(statusLabel);

            urlBox = new TextBox();
            urlBox.Left = 18;
            urlBox.Top = 90;
            urlBox.Width = 560;
            urlBox.ReadOnly = true;
            urlBox.Text = BuildUrl(PreferredPort);
            root.Controls.Add(urlBox);

            openButton = new Button();
            openButton.Text = "Ouvrir Mikhmon";
            openButton.Left = 18;
            openButton.Top = 128;
            openButton.Width = 130;
            openButton.Height = 34;
            openButton.Click += delegate { OpenBrowser(); };
            root.Controls.Add(openButton);

            restartButton = new Button();
            restartButton.Text = "Redemarrer";
            restartButton.Left = 158;
            restartButton.Top = 128;
            restartButton.Width = 110;
            restartButton.Height = 34;
            restartButton.Click += delegate
            {
                SetButtons(false);
                ThreadPool.QueueUserWorkItem(delegate
                {
                    StopOwnedServer();
                    StartWorkflow(true);
                });
            };
            root.Controls.Add(restartButton);

            stopButton = new Button();
            stopButton.Text = "Arreter";
            stopButton.Left = 278;
            stopButton.Top = 128;
            stopButton.Width = 100;
            stopButton.Height = 34;
            stopButton.Click += delegate
            {
                StopOwnedServer();
                SetStatus("Serveur arrete.");
            };
            root.Controls.Add(stopButton);

            logButton = new Button();
            logButton.Text = "Journal";
            logButton.Left = 388;
            logButton.Top = 128;
            logButton.Width = 90;
            logButton.Height = 34;
            logButton.Click += delegate { OpenLogFile(); };
            root.Controls.Add(logButton);

            Button quitButton = new Button();
            quitButton.Text = "Quitter";
            quitButton.Left = 488;
            quitButton.Top = 128;
            quitButton.Width = 90;
            quitButton.Height = 34;
            quitButton.Click += delegate { Close(); };
            root.Controls.Add(quitButton);

            logBox = new TextBox();
            logBox.Left = 18;
            logBox.Top = 178;
            logBox.Width = 560;
            logBox.Height = 150;
            logBox.Multiline = true;
            logBox.ScrollBars = ScrollBars.Vertical;
            logBox.ReadOnly = true;
            root.Controls.Add(logBox);
        }

        private void InitializeTray()
        {
            trayIcon = new NotifyIcon();
            trayIcon.Text = "MIKHMON PRO ADMIN";
            trayIcon.Icon = Icon == null ? SystemIcons.Application : Icon;
            trayIcon.Visible = false;
            trayIcon.DoubleClick += delegate
            {
                Show();
                WindowState = FormWindowState.Normal;
                Activate();
            };

            ContextMenuStrip menu = new ContextMenuStrip();
            menu.Items.Add("Ouvrir Mikhmon", null, delegate { OpenBrowser(); });
            menu.Items.Add("Afficher le lanceur", null, delegate
            {
                Show();
                WindowState = FormWindowState.Normal;
                Activate();
            });
            menu.Items.Add("Quitter", null, delegate { Close(); });
            trayIcon.ContextMenuStrip = menu;
        }

        private void StartWorkflow(bool openWhenReady)
        {
            AppendLog("Demarrage du lanceur depuis: " + appRoot);
            SetButtons(false);

            try
            {
                ValidateFiles();
                int port = ChoosePort();
                activePort = port;
                SetUrl(BuildUrl(activePort));

                if (IsPortOpen(activePort) && LooksLikeMikhmon(activePort))
                {
                    ownsServer = false;
                    SetStatus("Mikhmon est deja disponible sur le port " + activePort + ".");
                    AppendLog("Serveur deja actif sur " + BuildUrl(activePort));
                    if (openWhenReady)
                    {
                        OpenBrowser();
                    }
                    return;
                }

                StartPhpServer(activePort);
                WaitForServer(activePort);
                SetStatus("Serveur demarre sur le port " + activePort + ".");
                AppendLog("Serveur pret: " + BuildUrl(activePort));
                if (openWhenReady)
                {
                    OpenBrowser();
                }
            }
            catch (Exception ex)
            {
                SetStatus("Erreur: " + ex.Message);
                AppendLog("ERREUR: " + ex);
                MessageBox.Show(ex.Message, "MIKHMON PRO ADMIN", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
            finally
            {
                SetButtons(true);
            }
        }

        private void ValidateFiles()
        {
            if (!File.Exists(phpExe))
            {
                throw new FileNotFoundException("PHP 8 introuvable: " + phpExe);
            }
            if (!File.Exists(phpIni))
            {
                throw new FileNotFoundException("Configuration PHP 8 introuvable: " + phpIni);
            }
            if (!Directory.Exists(docRoot))
            {
                throw new DirectoryNotFoundException("Dossier Mikhmon introuvable: " + docRoot);
            }
            if (!File.Exists(Path.Combine(docRoot, "admin.php")))
            {
                throw new FileNotFoundException("admin.php introuvable dans: " + docRoot);
            }
        }

        private string ResolveLogPath()
        {
            string preferred = Path.Combine(appRoot, "mikhmon", "share", "logs", "lanceur.log");
            if (CanAppendLog(preferred))
            {
                return preferred;
            }

            string fallbackDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "MIKHMON_PRO_ADMIN");
            string fallback = Path.Combine(fallbackDir, "lanceur.log");
            CanAppendLog(fallback);
            return fallback;
        }

        private bool CanAppendLog(string path)
        {
            try
            {
                string dir = Path.GetDirectoryName(path);
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                }
                using (FileStream stream = new FileStream(path, FileMode.Append, FileAccess.Write, FileShare.ReadWrite))
                {
                }
                return true;
            }
            catch
            {
                return false;
            }
        }

        private int ChoosePort()
        {
            for (int port = PreferredPort; port <= LastPort; port++)
            {
                if (!IsPortOpen(port) || LooksLikeMikhmon(port))
                {
                    return port;
                }
            }

            throw new InvalidOperationException("Aucun port libre trouve entre " + PreferredPort + " et " + LastPort + ".");
        }

        private void StartPhpServer(int port)
        {
            Directory.CreateDirectory(Path.GetDirectoryName(logPath));

            ProcessStartInfo psi = new ProcessStartInfo();
            psi.FileName = phpExe;
            psi.WorkingDirectory = appRoot;
            psi.Arguments = "-c " + Quote(phpIni) + " -S " + Host + ":" + port + " -t " + Quote(docRoot);
            psi.UseShellExecute = false;
            psi.CreateNoWindow = true;
            psi.WindowStyle = ProcessWindowStyle.Hidden;
            psi.RedirectStandardOutput = true;
            psi.RedirectStandardError = true;

            serverProcess = new Process();
            serverProcess.StartInfo = psi;
            serverProcess.EnableRaisingEvents = true;
            serverProcess.OutputDataReceived += delegate(object sender, DataReceivedEventArgs e)
            {
                if (!String.IsNullOrEmpty(e.Data))
                {
                    AppendLog("[php] " + e.Data);
                }
            };
            serverProcess.ErrorDataReceived += delegate(object sender, DataReceivedEventArgs e)
            {
                if (!String.IsNullOrEmpty(e.Data))
                {
                    AppendLog("[php] " + e.Data);
                }
            };
            serverProcess.Exited += delegate
            {
                if (ownsServer)
                {
                    SetStatus("Serveur PHP arrete.");
                    AppendLog("Processus PHP arrete.");
                }
            };

            serverProcess.Start();
            serverProcess.BeginOutputReadLine();
            serverProcess.BeginErrorReadLine();
            ownsServer = true;
            AppendLog("Commande PHP: " + psi.FileName + " " + psi.Arguments);
        }

        private void WaitForServer(int port)
        {
            DateTime deadline = DateTime.Now.AddSeconds(20);
            while (DateTime.Now < deadline)
            {
                if (serverProcess != null && serverProcess.HasExited)
                {
                    throw new InvalidOperationException("PHP s'est arrete avant le demarrage du serveur.");
                }
                if (LooksLikeMikhmon(port))
                {
                    return;
                }
                Thread.Sleep(500);
            }

            throw new TimeoutException("Le serveur PHP ne repond pas apres 20 secondes.");
        }

        private bool LooksLikeMikhmon(int port)
        {
            try
            {
                HttpWebRequest request = (HttpWebRequest)WebRequest.Create(BuildUrl(port));
                request.Method = "GET";
                request.Timeout = 1800;
                request.ReadWriteTimeout = 1800;
                request.AllowAutoRedirect = true;
                using (HttpWebResponse response = (HttpWebResponse)request.GetResponse())
                {
                    if ((int)response.StatusCode < 200 || (int)response.StatusCode >= 400)
                    {
                        return false;
                    }
                    using (StreamReader reader = new StreamReader(response.GetResponseStream()))
                    {
                        string body = reader.ReadToEnd();
                        return body.IndexOf("Mikhmon", StringComparison.OrdinalIgnoreCase) >= 0
                            || body.IndexOf("MIKHMON", StringComparison.OrdinalIgnoreCase) >= 0
                            || body.IndexOf("admin.php", StringComparison.OrdinalIgnoreCase) >= 0;
                    }
                }
            }
            catch
            {
                return false;
            }
        }

        private bool IsPortOpen(int port)
        {
            TcpClient client = null;
            try
            {
                client = new TcpClient();
                IAsyncResult result = client.BeginConnect(Host, port, null, null);
                bool connected = result.AsyncWaitHandle.WaitOne(500);
                if (!connected)
                {
                    return false;
                }
                client.EndConnect(result);
                return client.Connected;
            }
            catch
            {
                return false;
            }
            finally
            {
                if (client != null)
                {
                    client.Close();
                }
            }
        }

        private void StopOwnedServer()
        {
            try
            {
                if (ownsServer && serverProcess != null && !serverProcess.HasExited)
                {
                    AppendLog("Arret du serveur PHP.");
                    serverProcess.Kill();
                    serverProcess.WaitForExit(3000);
                }
            }
            catch (Exception ex)
            {
                AppendLog("Erreur pendant l'arret: " + ex.Message);
            }
            finally
            {
                ownsServer = false;
            }
        }

        private void OpenBrowser()
        {
            try
            {
                if (activePort <= 0)
                {
                    activePort = PreferredPort;
                }
                ProcessStartInfo psi = new ProcessStartInfo();
                psi.FileName = BuildUrl(activePort);
                psi.UseShellExecute = true;
                Process.Start(psi);
            }
            catch (Exception ex)
            {
                AppendLog("Impossible d'ouvrir le navigateur: " + ex.Message);
                MessageBox.Show("Impossible d'ouvrir le navigateur.\r\n" + BuildUrl(activePort), "MIKHMON PRO ADMIN", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            }
        }

        private void OpenLogFile()
        {
            try
            {
                Directory.CreateDirectory(Path.GetDirectoryName(logPath));
                if (!File.Exists(logPath))
                {
                    File.WriteAllText(logPath, "");
                }
                Process.Start("notepad.exe", Quote(logPath));
            }
            catch (Exception ex)
            {
                MessageBox.Show(ex.Message, "MIKHMON PRO ADMIN", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            }
        }

        private string BuildUrl(int port)
        {
            return "http://" + Host + ":" + port + "/admin.php?id=login";
        }

        private string Quote(string value)
        {
            return "\"" + value.Replace("\"", "\\\"") + "\"";
        }

        private void SetButtons(bool enabled)
        {
            if (InvokeRequired)
            {
                BeginInvoke(new Action<bool>(SetButtons), enabled);
                return;
            }

            openButton.Enabled = enabled;
            restartButton.Enabled = enabled;
            stopButton.Enabled = enabled;
            logButton.Enabled = enabled;
        }

        private void SetStatus(string text)
        {
            if (InvokeRequired)
            {
                BeginInvoke(new Action<string>(SetStatus), text);
                return;
            }
            statusLabel.Text = text;
        }

        private void SetUrl(string text)
        {
            if (InvokeRequired)
            {
                BeginInvoke(new Action<string>(SetUrl), text);
                return;
            }
            urlBox.Text = text;
        }

        private void AppendLog(string text)
        {
            string line = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss") + " " + text;
            try
            {
                Directory.CreateDirectory(Path.GetDirectoryName(logPath));
                File.AppendAllText(logPath, line + Environment.NewLine);
            }
            catch
            {
            }

            if (logBox == null)
            {
                return;
            }

            if (InvokeRequired)
            {
                BeginInvoke(new Action<string>(AppendLogToBox), line);
            }
            else
            {
                AppendLogToBox(line);
            }
        }

        private void AppendLogToBox(string line)
        {
            logBox.AppendText(line + Environment.NewLine);
        }
    }
}
