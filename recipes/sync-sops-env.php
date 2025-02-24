<?php
namespace Deployer;

task('sync:sops-env', function () {
    if (PHP_OS_FAMILY !== 'Linux') {
        writeln("⚠️ Task skipped: Not a Linux server.");
        return;
    }
    $shared_path = "{{deploy_path}}/shared";
    try {
        // Create temporary files
        $temp_env_curr_file = tempnam(sys_get_temp_dir(), get('application') . "_env_curr_");
        $temp_env_new_file = tempnam(sys_get_temp_dir(), get('application') . "_env_new_");

        if (!$temp_env_curr_file || !$temp_env_new_file) {
            throw new RuntimeException('Failed to create temporary files.');
        }

        // Decrypt the new environment file
        $sops_env_file = get('sops_env_file');
        if (testLocally("[ -f $sops_env_file ]") === false) {
            writeln("❌ sops env file: $sops_env_file not found");
            return -1;
        }
        runLocally("sops -d $sops_env_file > $temp_env_new_file");

        // Download the existing remote .env file
        writeln("🔄 Fetching .env file from target host " . get('hostname'));
        download("$shared_path/.env", $temp_env_curr_file);

        // Compare the current and new .env files
        $diff = runLocally("diff --report-identical-files -y $temp_env_new_file $temp_env_curr_file || true");
        if (strpos($diff, "are identical") !== false) {
            writeln("✅ No differences to $sops_env_file found. Skipping upload.");
        } else {
            writeln($diff);
            writeln("⚠️ Differences detected!");
            if (askConfirmation("Differences found! Are you sure you want to proceed with uploading the new file? This will overwrite the existing one.")) {
                writeln("📤 Uploading new configuration...");
                upload($temp_env_new_file, "$shared_path/.env");
            } else {
                writeln("❌ Upload canceled.");
            }
        }
    } finally {
        unlink($temp_env_curr_file);
        unlink($temp_env_new_file);
    }
})->desc('decrypt sops.env file and upload to target server');
