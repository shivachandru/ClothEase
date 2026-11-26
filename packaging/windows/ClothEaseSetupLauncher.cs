using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.IO.Compression;
using System.Reflection;
using System.Security.Cryptography;
using System.Text;
using System.Collections.Generic;
using System.Threading.Tasks;
using System.Windows.Forms;

internal static class ClothEaseSetupLauncher
{
    private const string PackageVersion = "__PACKAGE_VERSION__";
    private const string InstallKeyHash = "__INSTALL_KEY_HASH__";
    private const string ClientName = "__CLIENT_NAME__";
    private static Form window;
    private static TextBox output;
    private static TextBox installKey;
    private static Label status;
    private static ProgressBar progress;
    private static Button installButton;
    private static Button uninstallButton;
    private static Button closeButton;
    private static LinkLabel xamppLink;
    private static LinkLabel phpLink;
    private static LinkLabel runtimeLink;
    private static string phpExecutable;
    private static bool prerequisitesReady;
    private static string stage;
    private static bool existingInstallation;
    private static bool installedProgram;
    private static bool installationBlocked;
    private static string installedVersion;

    [STAThread]
    private static void Main()
    {
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);

        window = new Form();
        window.Text = ClientName + " Setup";
        window.Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath);
        window.StartPosition = FormStartPosition.CenterScreen;
        window.ClientSize = new Size(600, 488);
        window.MinimumSize = new Size(600, 488);
        window.FormBorderStyle = FormBorderStyle.FixedDialog;
        window.MaximizeBox = false;

        Label heading = new Label();
        heading.Text = ClientName + " setup - version " + PackageVersion;
        heading.Font = new Font("Segoe UI", 16F, FontStyle.Bold);
        heading.Location = new Point(22, 18);
        heading.Size = new Size(540, 34);
        window.Controls.Add(heading);

        Label details = new Label();
        details.Text = "Setup checks PHP 8.2+ and required extensions before making changes. " +
            "ClothEase runs on this computer only; shop data stays under your Windows user profile.";
        details.Font = new Font("Segoe UI", 9F);
        details.Location = new Point(24, 60);
        details.Size = new Size(545, 44);
        window.Controls.Add(details);

        status = new Label();
        status.Text = "Ready to install.";
        status.Font = new Font("Segoe UI", 9F, FontStyle.Bold);
        Label keyLabel = new Label();
        keyLabel.Text = "Installation passkey";
        keyLabel.Font = new Font("Segoe UI", 9F);
        keyLabel.Location = new Point(24, 111);
        keyLabel.Size = new Size(140, 24);
        window.Controls.Add(keyLabel);

        installKey = new TextBox();
        installKey.Location = new Point(165, 108);
        installKey.Size = new Size(250, 25);
        installKey.UseSystemPasswordChar = true;
        installKey.TextChanged += delegate { UpdateInstallButton(); };
        window.Controls.Add(installKey);

        status.Location = new Point(24, 143);
        status.Size = new Size(545, 22);
        window.Controls.Add(status);

        progress = new ProgressBar();
        progress.Location = new Point(24, 169);
        progress.Size = new Size(545, 18);
        progress.Style = ProgressBarStyle.Blocks;
        window.Controls.Add(progress);

        output = new TextBox();
        output.Location = new Point(24, 199);
        output.Size = new Size(545, 145);
        output.Multiline = true;
        output.ReadOnly = true;
        output.ScrollBars = ScrollBars.Vertical;
        output.Font = new Font("Consolas", 9F);
        output.Text = "This installer checks the installed version and requires the installation passkey.";
        window.Controls.Add(output);

        xamppLink = new LinkLabel();
        xamppLink.Text = "Download XAMPP (recommended; includes PHP)";
        xamppLink.Location = new Point(24, 350);
        xamppLink.Size = new Size(350, 20);
        xamppLink.Visible = false;
        xamppLink.LinkClicked += delegate { OpenLink("https://www.apachefriends.org/download.html"); };
        window.Controls.Add(xamppLink);

        phpLink = new LinkLabel();
        phpLink.Text = "Download PHP for Windows (standalone)";
        phpLink.Location = new Point(24, 371);
        phpLink.Size = new Size(350, 20);
        phpLink.Visible = false;
        phpLink.LinkClicked += delegate { OpenLink("https://www.php.net/downloads.php?os=windows&osvariant=windows-downloads"); };
        window.Controls.Add(phpLink);

        runtimeLink = new LinkLabel();
        runtimeLink.Text = "Microsoft Visual C++ runtime (if standalone PHP will not start)";
        runtimeLink.Location = new Point(24, 392);
        runtimeLink.Size = new Size(440, 20);
        runtimeLink.Visible = false;
        runtimeLink.LinkClicked += delegate { OpenLink("https://aka.ms/vc14/vc_redist.x64.exe"); };
        window.Controls.Add(runtimeLink);

        installButton = new Button();
        installButton.Text = "Install";
        installButton.Location = new Point(378, 444);
        installButton.Size = new Size(90, 30);
        installButton.Click += delegate { BeginInstall(); };
        window.Controls.Add(installButton);

        uninstallButton = new Button();
        uninstallButton.Text = "Uninstall";
        uninstallButton.Location = new Point(277, 444);
        uninstallButton.Size = new Size(90, 30);
        uninstallButton.Visible = false;
        uninstallButton.Click += delegate { BeginUninstall(); };
        window.Controls.Add(uninstallButton);

        closeButton = new Button();
        closeButton.Text = "Close";
        closeButton.Location = new Point(479, 444);
        closeButton.Size = new Size(90, 30);
        closeButton.Enabled = false;
        closeButton.Click += delegate { window.Close(); };
        window.Controls.Add(closeButton);

        ConfigureExistingInstallation();
        CheckRuntimePrerequisites();
        UpdateInstallButton();

        Application.Run(window);
    }

    private static void BeginInstall()
    {
        if (!CheckRuntimePrerequisites())
            return;

        string warning = existingInstallation
            ? "A ClothEase installation or data folder already exists. Update to version " + PackageVersion + "?\r\n\r\nThe program files will be updated. The database and backups under %LOCALAPPDATA%\\ClothEase will be preserved. Make a backup before continuing."
            : "Install ClothEase version " + PackageVersion + " on this computer? Shop data will be stored under %LOCALAPPDATA%\\ClothEase.";
        if (MessageBox.Show(warning, "Confirm ClothEase setup", MessageBoxButtons.YesNo, MessageBoxIcon.Warning) != DialogResult.Yes)
            return;

        if (!IsCorrectKey(installKey.Text))
        {
            MessageBox.Show("The installation passkey is incorrect.", "ClothEase setup", MessageBoxButtons.OK, MessageBoxIcon.Error);
            installKey.Clear();
            installKey.Focus();
            return;
        }

        installKey.Clear();

        installButton.Enabled = false;
        uninstallButton.Enabled = false;
        installKey.Enabled = false;
        closeButton.Enabled = false;
        progress.Style = ProgressBarStyle.Marquee;
        status.Text = "Preparing the installer...";
        output.Clear();
        Append("Unpacking ClothEase test files...");

        Task.Factory.StartNew(Install, TaskCreationOptions.LongRunning);
    }

    private static void Install()
    {
        int exitCode = 1;
        string failure = null;
        stage = Path.Combine(Path.GetTempPath(), "ClothEase-Setup-" + Guid.NewGuid().ToString("N"));

        try
        {
            Directory.CreateDirectory(stage);
            string archive = Path.Combine(stage, "ClothEase-Test-Installer.zip");
            using (Stream embedded = Assembly.GetExecutingAssembly().GetManifestResourceStream("ClothEase-Test-Installer.zip"))
            {
                if (embedded == null)
                    throw new InvalidOperationException("The embedded ClothEase package is missing.");
                using (FileStream file = File.Create(archive))
                    embedded.CopyTo(file);
            }

            string package = Path.Combine(stage, "Package");
            ZipFile.ExtractToDirectory(archive, package);
            string installer = Path.Combine(package, "Install-ClothEase.ps1");
            using (Stream currentInstaller = Assembly.GetExecutingAssembly().GetManifestResourceStream("ClothEase-Install.ps1"))
            {
                if (currentInstaller == null)
                    throw new InvalidOperationException("The updated ClothEase installer script is missing.");
                using (FileStream file = File.Create(installer))
                    currentInstaller.CopyTo(file);
            }
            string appIcon = Path.Combine(stage, "ClothEase.ico");
            using (Stream embeddedIcon = Assembly.GetExecutingAssembly().GetManifestResourceStream("ClothEase-App.ico"))
            {
                if (embeddedIcon == null)
                    throw new InvalidOperationException("The ClothEase application icon is missing.");
                using (FileStream file = File.Create(appIcon))
                    embeddedIcon.CopyTo(file);
            }
            if (!File.Exists(installer))
                throw new FileNotFoundException("The package does not contain the ClothEase installer script.");

            SetStatus("Installing ClothEase...");
            Append("Running setup and database migrations...");

            string powershell = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.System),
                @"WindowsPowerShell\v1.0\powershell.exe");
            ProcessStartInfo start = new ProcessStartInfo();
            start.FileName = powershell;
            start.Arguments = "-NoProfile -ExecutionPolicy Bypass -File \"" + installer + "\" -PackageVersion " + PackageVersion +
                " -PhpPath \"" + phpExecutable + "\" -IconPath \"" + appIcon + "\"";
            start.WorkingDirectory = Path.Combine(package, "App");
            start.UseShellExecute = false;
            start.CreateNoWindow = true;
            start.RedirectStandardOutput = true;
            start.RedirectStandardError = true;
            start.RedirectStandardInput = true;

            using (Process child = new Process())
            {
                child.StartInfo = start;
                child.OutputDataReceived += delegate(object sender, DataReceivedEventArgs args) { if (args.Data != null) Append(args.Data); };
                child.ErrorDataReceived += delegate(object sender, DataReceivedEventArgs args) { if (args.Data != null) Append(args.Data); };
                if (!child.Start())
                    throw new InvalidOperationException("Could not start Windows PowerShell.");
                child.BeginOutputReadLine();
                child.BeginErrorReadLine();
                child.WaitForExit();
                child.WaitForExit();
                exitCode = child.ExitCode;
            }

            if (exitCode != 0)
                throw new InvalidOperationException("Setup stopped with exit code " + exitCode + ". Check the messages above.");
        }
        catch (Exception error)
        {
            failure = error.Message;
        }
        finally
        {
            try
            {
                if (stage != null && Directory.Exists(stage))
                    Directory.Delete(stage, true);
            }
            catch
            {
                // Windows can clean up temporary setup files later if they are still in use.
            }
        }

        int finalExitCode = exitCode;
        string finalFailure = failure;
        window.BeginInvoke((MethodInvoker)delegate
        {
            progress.Style = ProgressBarStyle.Blocks;
            progress.Value = finalExitCode == 0 && finalFailure == null ? 100 : 0;
            if (finalExitCode == 0 && finalFailure == null)
            {
                existingInstallation = true;
                installedProgram = true;
                installationBlocked = true;
                uninstallButton.Visible = true;
                status.Text = "Installation complete.";
                Append("ClothEase is installed. Use the ClothEase desktop shortcut to open it.");
                Append("Shop data folder: %LOCALAPPDATA%\\ClothEase");
            }
            else
            {
                status.Text = "Installation failed.";
                Append("ERROR: " + finalFailure);
            }
            installKey.Enabled = true;
            UpdateInstallButton();
            closeButton.Enabled = true;
            closeButton.Focus();
        });
    }

    private static void ConfigureExistingInstallation()
    {
        string local = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
        string dataRoot = Path.Combine(local, "ClothEase");
        string installRoot = Path.Combine(local, "Programs", "ClothEase");
        string dataMarker = Path.Combine(dataRoot, "install-state.txt");
        string programMarker = Path.Combine(installRoot, "install-state.txt");
        installedProgram = File.Exists(programMarker) || File.Exists(Path.Combine(installRoot, "artisan"));
        uninstallButton.Visible = installedProgram;
        existingInstallation = File.Exists(dataMarker) || File.Exists(programMarker) || File.Exists(Path.Combine(dataRoot, ".env")) ||
            File.Exists(Path.Combine(dataRoot, "database.sqlite")) || installedProgram;

        if (!existingInstallation)
        {
            status.Text = "New installation. Enter the passkey to continue.";
            return;
        }

        installedVersion = ReadHighestInstalledVersion(dataMarker, programMarker);
        Version installed;
        Version package;
        bool knownInstalledVersion = Version.TryParse(installedVersion, out installed);
        bool knownPackageVersion = Version.TryParse(PackageVersion, out package);
        installationBlocked = knownInstalledVersion && knownPackageVersion && installed.CompareTo(package) >= 0;

        if (installationBlocked)
        {
            status.Text = "Already installed (version " + installedVersion + "). Reinstallation is blocked.";
            output.Text = "This version or a newer version is already installed.\r\n\r\nTo update, use an installer with a higher version number.";
            installButton.Enabled = false;
            return;
        }

        status.Text = "Existing ClothEase data detected. Confirm the update before continuing.";
        installButton.Text = "Update";
        output.Text = "Installed version: " + installedVersion + "\r\nUpdate version: " + PackageVersion +
            "\r\n\r\nUpdating keeps the separate database and backup folder.";
    }

    private static string ReadHighestInstalledVersion(string dataMarker, string programMarker)
    {
        Version highest = null;
        foreach (string marker in new string[] { dataMarker, programMarker })
        {
            if (!File.Exists(marker))
                continue;
            Version candidate;
            if (Version.TryParse(File.ReadAllText(marker).Trim(), out candidate) &&
                (highest == null || candidate.CompareTo(highest) > 0))
                highest = candidate;
        }
        return highest == null ? "an earlier test version" : highest.ToString();
    }

    private static void UpdateInstallButton()
    {
        if (installButton != null && !installationBlocked)
            installButton.Enabled = prerequisitesReady && installKey != null && !String.IsNullOrWhiteSpace(installKey.Text);
        if (uninstallButton != null)
            uninstallButton.Enabled = installedProgram && installKey != null && !String.IsNullOrWhiteSpace(installKey.Text);
    }

    private static bool CheckRuntimePrerequisites()
    {
        List<string> candidates = new List<string>();
        candidates.Add(@"C:\xampp\php\php.exe");
        string programFiles = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles);
        string programFilesX86 = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86);
        if (!String.IsNullOrEmpty(programFiles))
        {
            candidates.Add(Path.Combine(programFiles, "PHP", "php.exe"));
            candidates.Add(Path.Combine(programFiles, "XAMPP", "php", "php.exe"));
        }
        if (!String.IsNullOrEmpty(programFilesX86))
            candidates.Add(Path.Combine(programFilesX86, "PHP", "php.exe"));
        foreach (DriveInfo drive in DriveInfo.GetDrives())
            if (drive.IsReady) candidates.Add(Path.Combine(drive.RootDirectory.FullName, "xampp", "php", "php.exe"));
        string path = Environment.GetEnvironmentVariable("PATH") ?? String.Empty;
        foreach (string folder in path.Split(Path.PathSeparator))
        {
            if (!String.IsNullOrWhiteSpace(folder))
                candidates.Add(Path.Combine(folder.Trim().Trim('"'), "php.exe"));
        }

        List<string> problems = new List<string>();
        HashSet<string> seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        phpExecutable = null;
        foreach (string candidate in candidates)
        {
            if (!seen.Add(candidate) || !File.Exists(candidate))
                continue;
            try
            {
                string versionText = RunPhp(candidate, "-r \"echo PHP_VERSION_ID;\"").Trim();
                int versionId;
                if (!Int32.TryParse(versionText, out versionId))
                {
                    problems.Add("PHP at " + candidate + " did not report its version.");
                    continue;
                }
                if (versionId < 80200)
                {
                    problems.Add("PHP " + (versionId / 10000) + "." + ((versionId / 100) % 100) + " at " + candidate + " is too old; version 8.2 or newer is required.");
                    continue;
                }

                string modulesText = RunPhp(candidate, "-m");
                HashSet<string> modules = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                foreach (string line in modulesText.Split(new char[] { '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries))
                    if (!String.IsNullOrWhiteSpace(line)) modules.Add(line.Trim());
                string[] required = new string[] { "mbstring", "openssl", "pdo_sqlite" };
                List<string> missing = new List<string>();
                foreach (string module in required)
                    if (!modules.Contains(module)) missing.Add(module);
                if (missing.Count > 0)
                {
                    problems.Add("PHP " + versionText + " at " + candidate + " is missing: " + String.Join(", ", missing.ToArray()) + ".");
                    continue;
                }

                phpExecutable = candidate;
                prerequisitesReady = true;
                xamppLink.Visible = false;
                phpLink.Visible = false;
                runtimeLink.Visible = false;
                status.Text = "PHP runtime ready (" + (versionId / 10000) + "." + ((versionId / 100) % 100) + ").";
                UpdateInstallButton();
                return true;
            }
            catch (Exception error)
            {
                problems.Add("Could not check " + candidate + ": " + error.Message);
            }
        }

        prerequisitesReady = false;
        string report = problems.Count == 0
            ? "PHP 8.2+ was not found. Install XAMPP or PHP for Windows, then run this setup again."
            : String.Join(Environment.NewLine, problems.ToArray()) + Environment.NewLine + Environment.NewLine +
                "ClothEase needs PHP 8.2+ with mbstring, openssl, and PDO SQLite. Install XAMPP or enable the missing extensions in php.ini, then run setup again.";
        status.Text = "Required PHP setup is missing. Installation is disabled.";
        output.Text = report;
        xamppLink.Visible = true;
        phpLink.Visible = true;
        runtimeLink.Visible = true;
        UpdateInstallButton();
        return false;
    }

    private static string RunPhp(string executable, string arguments)
    {
        ProcessStartInfo start = new ProcessStartInfo();
        start.FileName = executable;
        start.Arguments = arguments;
        start.UseShellExecute = false;
        start.CreateNoWindow = true;
        start.RedirectStandardOutput = true;
        start.RedirectStandardError = true;
        using (Process process = new Process())
        {
            process.StartInfo = start;
            if (!process.Start())
                throw new InvalidOperationException("PHP could not be started.");
            if (!process.WaitForExit(8000))
            {
                process.Kill();
                throw new TimeoutException("PHP prerequisite check timed out.");
            }
            string standardOutput = process.StandardOutput.ReadToEnd();
            string standardError = process.StandardError.ReadToEnd();
            if (process.ExitCode != 0)
                throw new InvalidOperationException(String.IsNullOrWhiteSpace(standardError) ? "PHP returned an error." : standardError.Trim());
            return standardOutput;
        }
    }

    private static void OpenLink(string url)
    {
        try { Process.Start(new ProcessStartInfo(url) { UseShellExecute = true }); }
        catch (Exception error)
        {
            MessageBox.Show("Could not open the download page. Copy this address into your browser:\r\n" + url + "\r\n\r\n" + error.Message,
                "ClothEase setup", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
    }

    private static void BeginUninstall()
    {
        if (MessageBox.Show(
            "Uninstall ClothEase program files and the desktop shortcut? Your shop database and backups under %LOCALAPPDATA%\\ClothEase will be kept.",
            "Confirm ClothEase uninstall",
            MessageBoxButtons.YesNo,
            MessageBoxIcon.Warning) != DialogResult.Yes)
            return;

        if (!IsCorrectKey(installKey.Text))
        {
            MessageBox.Show("The installation passkey is incorrect.", "ClothEase uninstall", MessageBoxButtons.OK, MessageBoxIcon.Error);
            installKey.Clear();
            installKey.Focus();
            return;
        }

        installKey.Clear();
        installKey.Enabled = false;
        installButton.Enabled = false;
        uninstallButton.Enabled = false;
        closeButton.Enabled = false;
        progress.Style = ProgressBarStyle.Marquee;
        status.Text = "Uninstalling ClothEase...";
        output.Clear();
        Append("Your database and backups will be preserved.");
        Task.Factory.StartNew(Uninstall, TaskCreationOptions.LongRunning);
    }

    private static void Uninstall()
    {
        int exitCode = 1;
        string failure = null;
        stage = Path.Combine(Path.GetTempPath(), "ClothEase-Uninstall-" + Guid.NewGuid().ToString("N"));
        try
        {
            Directory.CreateDirectory(stage);
            string uninstaller = Path.Combine(stage, "Uninstall-ClothEase.ps1");
            using (Stream embedded = Assembly.GetExecutingAssembly().GetManifestResourceStream("ClothEase-Uninstall.ps1"))
            {
                if (embedded == null)
                    throw new InvalidOperationException("The ClothEase uninstaller is missing from this setup package.");
                using (FileStream file = File.Create(uninstaller))
                    embedded.CopyTo(file);
            }

            SetStatus("Removing ClothEase program files...");
            Append("Stopping ClothEase and removing its program folder...");
            string powershell = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.System),
                @"WindowsPowerShell\v1.0\powershell.exe");
            ProcessStartInfo start = new ProcessStartInfo();
            start.FileName = powershell;
            start.Arguments = "-NoProfile -ExecutionPolicy Bypass -File \"" + uninstaller + "\"";
            start.UseShellExecute = false;
            start.CreateNoWindow = true;
            start.RedirectStandardOutput = true;
            start.RedirectStandardError = true;

            using (Process child = new Process())
            {
                child.StartInfo = start;
                child.OutputDataReceived += delegate(object sender, DataReceivedEventArgs args) { if (args.Data != null) Append(args.Data); };
                child.ErrorDataReceived += delegate(object sender, DataReceivedEventArgs args) { if (args.Data != null) Append(args.Data); };
                if (!child.Start())
                    throw new InvalidOperationException("Could not start Windows PowerShell.");
                child.BeginOutputReadLine();
                child.BeginErrorReadLine();
                child.WaitForExit();
                child.WaitForExit();
                exitCode = child.ExitCode;
            }
            if (exitCode != 0)
                throw new InvalidOperationException("Uninstall stopped with exit code " + exitCode + ". Check the messages above.");
        }
        catch (Exception error)
        {
            failure = error.Message;
        }
        finally
        {
            try { if (Directory.Exists(stage)) Directory.Delete(stage, true); }
            catch { }
        }

        int finalExitCode = exitCode;
        string finalFailure = failure;
        window.BeginInvoke((MethodInvoker)delegate
        {
            progress.Style = ProgressBarStyle.Blocks;
            progress.Value = finalExitCode == 0 && finalFailure == null ? 100 : 0;
            installKey.Enabled = true;
            if (finalExitCode == 0 && finalFailure == null)
            {
                existingInstallation = false;
                installedProgram = false;
                installationBlocked = false;
                uninstallButton.Visible = false;
                installButton.Text = "Install";
                status.Text = "ClothEase uninstalled. Shop data was kept.";
                output.Text = "The program files and desktop shortcut were removed.\r\nYour database and backups remain in %LOCALAPPDATA%\\ClothEase.";
            }
            else
            {
                status.Text = "Uninstall failed.";
                Append("ERROR: " + finalFailure);
            }
            UpdateInstallButton();
            closeButton.Enabled = true;
            closeButton.Focus();
        });
    }

    private static bool IsCorrectKey(string value)
    {
        using (SHA256 sha = SHA256.Create())
        {
            byte[] hash = sha.ComputeHash(Encoding.UTF8.GetBytes(value ?? String.Empty));
            StringBuilder hex = new StringBuilder(hash.Length * 2);
            foreach (byte item in hash)
                hex.Append(item.ToString("x2"));

            string actual = hex.ToString();
            if (actual.Length != InstallKeyHash.Length)
                return false;
            int difference = 0;
            for (int index = 0; index < actual.Length; index++)
                difference |= actual[index] ^ InstallKeyHash[index];
            return difference == 0;
        }
    }

    private static void SetStatus(string value)
    {
        if (window.IsHandleCreated)
            window.BeginInvoke((MethodInvoker)delegate { status.Text = value; });
    }

    private static void Append(string value)
    {
        if (window.IsHandleCreated)
            window.BeginInvoke((MethodInvoker)delegate { output.AppendText(value + Environment.NewLine); });
    }
}
