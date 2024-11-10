# Completion script for db tool
complete -c db -f

# Main commands
complete -c db -n "not __fish_seen_subcommand_from cat cp diff ls ll" -a "cat cp diff ls ll" -d "DB tool command"

# Helper function to list config files
function __db_list_configs
    ls -1 ~/src/dbtool/config/
end

# Helper function to list tables using the db tool
function __db_list_tables
    set -l config $argv[1]
    command db ls $config
end

# Helper function to list fields using the db tool
function __db_list_fields
    set -l config $argv[1]
    set -l table $argv[2]
    command db ls $config $table
end

# Helper function to count arguments after command
function __db_count_args
    set -l cmd (commandline -poc)
    set -l cmd_count (count $cmd)
    if test $cmd_count -gt 1
        math $cmd_count - 1
    else
        echo 0
    end
end

# Helper function to check if a file exists in the configs directory
function __db_is_config_file
    set -l file $argv[1]
    test -f ~/src/dbtool/config/$file
end

# Helper function to list both configs and tables for cat command
function __db_list_configs_and_tables
    set -l configs (__db_list_configs)
    for config in $configs
        echo $config\tConfig file
    end

    # If we have a first config file, also list its tables
    set -l cmd (commandline -poc)
    if test (count $cmd) -gt 2
        set -l tables (__db_list_tables $cmd[3])
        for table in $tables
            echo $table\tTable name
        end
    end
end

# Completion for 'cat' command
complete -c db -n "__fish_seen_subcommand_from cat; and test (__db_count_args) -eq 1" \
    -a "(__db_list_configs)" -d "First config file"

complete -c db -n "__fish_seen_subcommand_from cat; and test (__db_count_args) -eq 2" \
    -a "(__db_list_configs_and_tables)" -f

complete -c db -n "__fish_seen_subcommand_from cat; and test (__db_count_args) -eq 3; and __db_is_config_file (commandline -poc)[4]" \
    -a "(__db_list_tables (commandline -poc)[3])" -d "Table name"

# Completion for 'cp' command
complete -c db -n "__fish_seen_subcommand_from cp; and test (__db_count_args) -eq 1" \
    -a "(__db_list_configs)" -d "Source config file"

complete -c db -n "__fish_seen_subcommand_from cp; and test (__db_count_args) -eq 2" \
    -a "(__db_list_configs)" -d "Destination config file"

complete -c db -n "__fish_seen_subcommand_from cp; and test (__db_count_args) -eq 3" \
    -a "(__db_list_tables (commandline -poc)[3])" -d "Table name"

# Completion for 'diff' command
complete -c db -n "__fish_seen_subcommand_from diff; and test (__db_count_args) -eq 1" \
    -a "(__db_list_configs)" -d "First config file"

complete -c db -n "__fish_seen_subcommand_from diff; and test (__db_count_args) -eq 2" \
    -a "(__db_list_configs)" -d "Second config file"

complete -c db -n "__fish_seen_subcommand_from diff; and test (__db_count_args) -eq 3" \
    -a "(__db_list_tables (commandline -poc)[3])" -d "Table name"

complete -c db -n "__fish_seen_subcommand_from diff; and test (__db_count_args) -eq 4" \
    -a "(__db_list_fields (commandline -poc)[3] (commandline -poc)[5])" -d "Field name"

# Completion for 'ls' and 'll' commands
complete -c db -n "__fish_seen_subcommand_from ls ll; and test (__db_count_args) -eq 1" \
    -a "(__db_list_configs)" -d "Config file"

complete -c db -n "__fish_seen_subcommand_from ls ll; and test (__db_count_args) -eq 2" \
    -a "(__db_list_tables (commandline -poc)[3])" -d "Table name"

complete -c db -n "__fish_seen_subcommand_from ls ll; and test (__db_count_args) -eq 3" \
    -a "(__db_list_fields (commandline -poc)[3] (commandline -poc)[4])" -d "Field name"
