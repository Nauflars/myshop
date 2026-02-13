#!/usr/bin/python3
"""
Custom Ansible module for executing commands inside Docker containers.
This module provides a clean interface for running commands in containers
as part of Ansible playbooks.
"""

from ansible.module_utils.basic import AnsibleModule
import subprocess


DOCUMENTATION = '''
---
module: docker_container_command
short_description: Execute commands inside Docker containers
description:
    - This module allows executing commands inside running Docker containers
    - It wraps docker exec with proper error handling and output capture
version_added: "1.0"
options:
    container:
        description:
            - Name or ID of the Docker container
        required: true
        type: str
    command:
        description:
            - Command to execute inside the container
        required: true
        type: str
    chdir:
        description:
            - Working directory inside the container
        required: false
        type: str
        default: null
    user:
        description:
            - User to execute command as
        required: false
        type: str
        default: null
author:
    - MyShop DevOps Team
'''

EXAMPLES = '''
# Execute a Composer command
- docker_container_command:
    container: myshop-test
    command: composer install --no-dev
    chdir: /var/www/myshop/current

# Run database migrations
- docker_container_command:
    container: myshop-prod
    command: php bin/console doctrine:migrations:migrate --no-interaction
    chdir: /var/www/myshop/current
'''


def run_command_in_container(container_name, command, chdir=None, user=None):
    """
    Execute command inside Docker container using docker exec.
    
    Args:
        container_name (str): Container name or ID
        command (str): Command to execute
        chdir (str, optional): Working directory
        user (str, optional): User to run as
        
    Returns:
        tuple: (return_code, stdout, stderr)
    """
    cmd = ["docker", "exec"]
    
    if user:
        cmd.extend(["-u", user])
        
    if chdir:
        cmd.extend(["-w", chdir])
        
    cmd.append(container_name)
    
    # Split command if it's a string, or use as-is if it's a list
    if isinstance(command, str):
        cmd.extend(command.split())
    else:
        cmd.extend(command)
    
    try:
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=300  # 5 minute timeout
        )
        return result.returncode, result.stdout, result.stderr
    except subprocess.TimeoutExpired:
        return 1, "", "Command timed out after 5 minutes"
    except Exception as e:
        return 1, "", str(e)


def main():
    """Main module execution."""
    module = AnsibleModule(
        argument_spec=dict(
            container=dict(required=True, type='str'),
            command=dict(required=True, type='str'),
            chdir=dict(required=False, type='str', default=None),
            user=dict(required=False, type='str', default=None)
        ),
        supports_check_mode=False
    )
    
    container = module.params['container']
    command = module.params['command']
    chdir = module.params['chdir']
    user = module.params['user']
    
    # Execute the command
    rc, stdout, stderr = run_command_in_container(
        container,
        command,
        chdir,
        user
    )
    
    if rc == 0:
        module.exit_json(
            changed=True,
            stdout=stdout,
            stderr=stderr,
            rc=rc,
            cmd=command
        )
    else:
        module.fail_json(
            msg=f"Command failed with return code {rc}",
            stdout=stdout,
            stderr=stderr,
            rc=rc,
            cmd=command
        )


if __name__ == '__main__':
    main()
