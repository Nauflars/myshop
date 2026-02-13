# Ansible Inventory Contract Schema (Local Docker Mode)

**Version**: 2.0.0  
**Date**: 2026-02-13

## Overview

This document defines the contract structure for Ansible inventories used in the MyShop **local Docker-based** CI/CD pipeline. It establishes the required inventory structure for deploying to Docker containers using `ansible_connection=local` instead of SSH connections. The pipeline simulates test and production environments as separate Docker containers running on the same host.

**Key Difference from Traditional Ansible**: No SSH connection required; deployments execute locally and interact with Docker containers via `docker exec` or mounted volumes.

---

## Directory Structure Contract

```
deployment/
├── ansible.cfg                       # Ansible configuration (connection=local)
├── local-deploy.yml                  # Main deployment playbook for Docker
├── rollback.yml                      # Rollback playbook
├── inventories/
│   ├── local-test/                  # Local test Docker container
│   │   ├── hosts                    # Test inventory (localhost)
│   │   └── group_vars/
│   │       ├── all.yml              # Non-sensitive test variables
│   │       └── all/
│   │           └── vault.yml        # Sensitive test variables (encrypted)
│   └── local-production/            # Local production Docker container
│       ├── hosts                    # Production inventory (localhost)
│       └── group_vars/
│           ├── all.yml              # Non-sensitive production variables
│           └── all/
│               └── vault.yml        # Sensitive production variables (encrypted)
├── library/
│   └── docker_container_command.py  # Custom module for Docker exec
├── roles/
│   └── requirements.yml             # External role dependencies
├── hooks/
│   ├── before-symlink-docker.yml    # Pre-deployment tasks (Docker-aware)
│   └── after-symlink-docker.yml     # Post-deployment tasks (Docker-aware)
└── vars/
    ├── common.yml                   # Variables common to all environments
    ├── docker-test.yml              # Docker test container variables
    └── docker-production.yml        # Docker production container variables
```

---

## Inventory Hosts File Contract (Local Docker)

### Test Environment (`inventories/local-test/hosts`)

```ini
[test]
localhost ansible_connection=local

[test:vars]
# Container identification
target_container=myshop-test
container_port=8081

# Deployment configuration
ansible_python_interpreter=/usr/bin/python3
env_name=test
app_domain=localhost
app_url=http://localhost:8081
deploy_via=copy
ansistrano_deploy_to=/var/www/myshop
execute_in_container=true

# Docker-specific paths (mounted volumes)
docker_volume_releases=test_releases
docker_volume_shared=test_shared
```

**Requirements**:
- **ansible_connection**: MUST be `local` (no SSH)
- **target_conta USER**: Name of Docker container to deploy to
- **container_port**: External port for accessing the container
- **execute_in_container**: Boolean flag to use docker exec for commands

### Production Environment (`inventories/local-production/hosts`)

```ini
[production]
localhost ansible_connection=local

[production:vars]
# Container identification
target_container=myshop-prod
container_port=8082

# Deployment configuration
ansible_python_interpreter=/usr/bin/python3
env_name=prod
app_domain=localhost
app_url=http://localhost:8082
deploy_via=copy
ansistrano_deploy_to=/var/www/myshop
execute_in_container=true

# Docker-specific paths (mounted volumes)
docker_volume_releases=prod_releases
docker_volume_shared=prod_shared
```

**Requirements**:
- **Separate containers**: Test and production run in isolated Docker containers
- **Different ports**: Test (8081), Production (8082)
- **Isolated volumes**: Each environment has dedicated Docker volumes
- **No SSH**: All operations use local connection and Docker exec

---

## Group Variables Contract

### Non-Sensitive Variables (`group_vars/all.yml`)

**Test Environment**:
```yaml
---
# Environment identification
env_name: test
app_env: test
app_debug: true
app_domain: test.myshop.com

# Application deployment
ansistrano_deploy_to: /var/www/myshop
ansistrano_deploy_via: git
ansistrano_git_repo: git@github.com:yourorg/myshop.git
ansistrano_git_branch: "{{ branch | default('master') }}"
ansistrano_keep_releases: 3

# Shared paths (persistent across deployments)
ansistrano_shared_paths:
  - var/log
  - var/sessions
  - public/uploads

ansistrano_shared_files:
  - .env.local

# Writable directories
ansistrano_writable_dirs:
  - var/cache
  - var/log
  - var/sessions

# Hooks
ansistrano_before_symlink_tasks_file: "{{ playbook_dir }}/hooks/before-symlink.yml"
ansistrano_after_symlink_tasks_file: "{{ playbook_dir }}/hooks/after-symlink.yml"

# PHP-FPM
php_fpm_service: php8.3-fpm
php_fpm_pool_user: www-data
php_fpm_pool_group: www-data

# Database
database_host: localhost
database_port: 3306
database_name: myshop_test
database_user: myshop_user

# Redis
redis_host: localhost
redis_port: 6379

# MongoDB
mongodb_host: localhost
mongodb_port: 27017
mongodb_database: myshop_test

# RabbitMQ
rabbitmq_host: localhost
rabbitmq_port: 5672
rabbitmq_vhost: /

# Application URLs
default_uri: "https://{{ app_domain }}"
```

**Production Environment**:
```yaml
---
# Environment identification
env_name: production
app_env: prod
app_debug: false
app_domain: myshop.com

# Application deployment
ansistrano_deploy_to: /var/www/myshop
ansistrano_deploy_via: git
ansistrano_git_repo: git@github.com:yourorg/myshop.git
ansistrano_git_branch: "{{ branch | default('master') }}"
ansistrano_keep_releases: 5  # Keep more releases in production for rollback

# Shared paths (same as test)
ansistrano_shared_paths:
  - var/log
  - var/sessions
  - public/uploads

ansistrano_shared_files:
  - .env.local

# Writable directories
ansistrano_writable_dirs:
  - var/cache
  - var/log
  - var/sessions

# Hooks
ansistrano_before_symlink_tasks_file: "{{ playbook_dir }}/hooks/before-symlink.yml"
ansistrano_after_symlink_tasks_file: "{{ playbook_dir }}/hooks/after-symlink.yml"

# PHP-FPM
php_fpm_service: php8.3-fpm
php_fpm_pool_user: www-data
php_fpm_pool_group: www-data

# Database (use vault variables for sensitive data)
database_host: "{{ vault_database_host }}"
database_port: 3306
database_name: myshop_production
database_user: "{{ vault_database_user }}"

# Redis
redis_host: "{{ vault_redis_host }}"
redis_port: 6379

# MongoDB
mongodb_host: "{{ vault_mongodb_host }}"
mongodb_port: 27017
mongodb_database: myshop_production

# RabbitMQ
rabbitmq_host: "{{ vault_rabbitmq_host }}"
rabbitmq_port: 5672
rabbitmq_vhost: /

# Application URLs
default_uri: "https://{{ app_domain }}"
```

---

### Sensitive Variables (Vault) (`group_vars/all/vault.yml`)

**Test Environment** (encrypted):
```yaml
---
# Database credentials
vault_database_host: 192.168.1.50
vault_database_user: myshop_user
vault_database_password: test_db_password_123

# Redis password
vault_redis_host: 192.168.1.51
vault_redis_password: test_redis_pass

# MongoDB credentials
vault_mongodb_host: 192.168.1.52
vault_mongodb_user: myshop_user
vault_mongodb_password: test_mongo_pass

# RabbitMQ credentials
vault_rabbitmq_host: 192.168.1.53
vault_rabbitmq_user: myshop
vault_rabbitmq_password: test_rabbit_pass

# Application secrets
vault_app_secret: test_symfony_secret_key_abc123xyz

# OpenAI API
vault_openai_api_key: sk-test-...

# JWT tokens (if applicable)
vault_jwt_secret: test_jwt_secret_456
```

**Production Environment** (encrypted):
```yaml
---
# Database credentials
vault_database_host: 10.0.1.10
vault_database_user: myshop_prod
vault_database_password: "{{ lookup('env', 'PROD_DB_PASSWORD') | default('CHANGE_ME') }}"

# Redis password
vault_redis_host: 10.0.1.20
vault_redis_password: "{{ lookup('env', 'PROD_REDIS_PASSWORD') | default('CHANGE_ME') }}"

# MongoDB credentials
vault_mongodb_host: 10.0.1.30
vault_mongodb_user: myshop_prod
vault_mongodb_password: "{{ lookup('env', 'PROD_MONGO_PASSWORD') | default('CHANGE_ME') }}"

# RabbitMQ credentials
vault_rabbitmq_host: 10.0.1.40
vault_rabbitmq_user: myshop_prod
vault_rabbitmq_password: "{{ lookup('env', 'PROD_RABBIT_PASSWORD') | default('CHANGE_ME') }}"

# Application secrets
vault_app_secret: "{{ lookup('env', 'PROD_APP_SECRET') | default('CHANGE_ME') }}"

# OpenAI API
vault_openai_api_key: "{{ lookup('env', 'PROD_OPENAI_KEY') | default('CHANGE_ME') }}"

# JWT tokens (if applicable)
vault_jwt_secret: "{{ lookup('env', 'PROD_JWT_SECRET') | default('CHANGE_ME') }}"
```

**Encryption Commands**:
```bash
# Create new encrypted file
ansible-vault create inventories/production/group_vars/all/vault.yml

# Edit encrypted file
ansible-vault edit inventories/production/group_vars/all/vault.yml

# Encrypt existing file
ansible-vault encrypt inventories/production/group_vars/all/vault.yml

# Decrypt for viewing (DO NOT COMMIT)
ansible-vault decrypt inventories/production/group_vars/all/vault.yml
```

---

## Playbook Contract

### Main Deployment Playbook (`deploy.yml`)

```yaml
---
- name: Deploy MyShop Application
  hosts: webservers
  become: yes
  become_user: "{{ deploy_user | default('deploy') }}"
  
  vars_files:
    - vars/common.yml
    - "vars/{{ env_name }}.yml"
  
  pre_tasks:
    - name: Verify deployment requirements
      assert:
        that:
          - ansistrano_deploy_to is defined
          - ansistrano_git_branch is defined
          - env_name in ['test', 'production']
        fail_msg: "Required deployment variables are missing"
    
    - name: Check disk space
      shell: df -h {{ ansistrano_deploy_to }} | awk 'NR==2 {print $5}' | sed 's/%//'
      register: disk_usage
      changed_when: false
    
    - name: Fail if disk usage > 80%
      fail:
        msg: "Disk usage is {{ disk_usage.stdout }}%, deployment aborted"
      when: disk_usage.stdout | int > 80
  
  roles:
    - role: ansistrano.deploy
      ansistrano_deploy_to: "{{ ansistrano_deploy_to }}"
      ansistrano_deploy_via: "{{ ansistrano_deploy_via }}"
      ansistrano_git_repo: "{{ ansistrano_git_repo }}"
      ansistrano_git_branch: "{{ ansistrano_git_branch }}"
  
  post_tasks:
    - name: Record deployment in history
      copy:
        content: |
          {
            "deployment_id": "{{ ansible_date_time.epoch }}",
            "environment": "{{ env_name }}",
            "commit": "{{ ansistrano_git_branch }}",
            "deployed_at": "{{ ansible_date_time.iso8601 }}",
            "deployed_by": "{{ lookup('env', 'USER') | default('jenkins') }}"
          }
        dest: "{{ ansistrano_deploy_to }}/.last_deployment.json"
    
    - name: Verify deployment
      uri:
        url: "https://{{ app_domain }}/health"
        status_code: 200
      register: health_check
      retries: 3
      delay: 5
      until: health_check.status == 200
```

**Contract Requirements**:
- MUST target `webservers` host group
- MUST use `become: yes` for privilege escalation
- MUST include pre-deployment checks (disk space, requirements)
- MUST record deployment metadata after success
- MUST verify health check endpoint after deployment

---

### Rollback Playbook (`rollback.yml`)

```yaml
---
- name: Rollback MyShop Application
  hosts: webservers
  become: yes
  become_user: "{{ deploy_user | default('deploy') }}"
  
  vars_files:
    - vars/common.yml
    - "vars/{{ env_name }}.yml"
  
  vars_prompt:
    - name: rollback_confirm
      prompt: "Are you sure you want to rollback {{ env_name }}? (yes/no)"
      private: no
  
  pre_tasks:
    - name: Abort if not confirmed
      fail:
        msg: "Rollback cancelled"
      when: rollback_confirm != 'yes'
    
    - name: Check if previous release exists
      stat:
        path: "{{ ansistrano_deploy_to }}/releases"
      register: releases_dir
    
    - name: Fail if no previous release
      fail:
        msg: "No previous releases found to rollback to"
      when: not releases_dir.stat.exists
  
  roles:
    - role: ansistrano.rollback
      ansistrano_deploy_to: "{{ ansistrano_deploy_to }}"
  
  post_tasks:
    - name: Reload PHP-FPM
      service:
        name: "{{ php_fpm_service }}"
        state: reloaded
    
    - name: Clear application cache
      command: php bin/console cache:clear --env={{ app_env }}
      args:
        chdir: "{{ ansistrano_deploy_to }}/current"
    
    - name: Record rollback in history
      copy:
        content: |
          {
            "rollback_id": "{{ ansible_date_time.epoch }}",
            "environment": "{{ env_name }}",
            "rolled_back_at": "{{ ansible_date_time.iso8601 }}",
            "rolled_back_by": "{{ lookup('env', 'USER') | default('jenkins') }}"
          }
        dest: "{{ ansistrano_deploy_to }}/.last_rollback.json"
    
    - name: Verify rollback
      uri:
        url: "https://{{ app_domain }}/health"
        status_code: 200
      register: health_check
      retries: 3
      delay: 5
```

**Contract Requirements**:
- MUST require confirmation before rollback
- MUST verify previous release exists
- MUST reload PHP-FPM after rollback
- MUST clear application cache
- MUST record rollback metadata

---

## Ansistrano Hooks Contract

### Before Symlink Hook (`hooks/before-symlink.yml`)

Executed BEFORE the new release is symlinked as `current`.

```yaml
---
- name: Create .env.local if not exists in shared
  copy:
    content: |
      APP_ENV={{ app_env }}
      APP_SECRET={{ vault_app_secret }}
      DATABASE_URL=mysql://{{ vault_database_user }}:{{ vault_database_password }}@{{ database_host }}:{{ database_port }}/{{ database_name }}?serverVersion=8.0
      REDIS_URL=redis://{{ redis_host }}:{{ redis_port }}
      MONGODB_URL=mongodb://{{ vault_mongodb_user }}:{{ vault_mongodb_password }}@{{ mongodb_host }}:{{ mongodb_port }}
      MONGODB_DATABASE={{ mongodb_database }}
      RABBITMQ_DSN=amqp://{{ vault_rabbitmq_user }}:{{ vault_rabbitmq_password }}@{{ rabbitmq_host }}:{{ rabbitmq_port }}/%2F
      DEFAULT_URI={{ default_uri }}
      OPENAI_API_KEY={{ vault_openai_api_key }}
    dest: "{{ ansistrano_shared_path }}/.env.local"
    force: no  # Don't overwrite if exists
  no_log: true  # Don't log secrets

- name: Install Composer dependencies
  composer:
    command: install
    working_dir: "{{ ansistrano_release_path.stdout }}"
    no_dev: "{{ app_env == 'prod' }}"
    optimize_autoloader: yes
  environment:
    COMPOSER_HOME: "{{ ansistrano_shared_path }}/.composer"

- name: Install Node dependencies (if package.json exists)
  command: npm ci --production
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"
  when: package_json_exists.stat.exists
  register: npm_result
  changed_when: "'added' in npm_result.stdout"

- name: Build frontend assets
  command: npm run build
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"
  when: npm_result is success
  environment:
    NODE_ENV: production

- name: Check for pending database migrations
  command: php bin/console doctrine:migrations:status --env={{ app_env }}
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"
  register: migration_status
  changed_when: false
  failed_when: false

- name: Run database migrations
  command: php bin/console doctrine:migrations:migrate --no-interaction --env={{ app_env }}
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"
  when: "'Available' in migration_status.stdout"
  register: migration_result
  failed_when: migration_result.rc != 0

- name: Abort deployment if migrations failed
  fail:
    msg: "Database migrations failed, aborting deployment"
  when: migration_result is defined and migration_result.failed

- name: Warm up Symfony cache
  command: php bin/console cache:warmup --env={{ app_env }}
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"

- name: Install Symfony assets
  command: php bin/console assets:install public --symlink --relative --env={{ app_env }}
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"

- name: Set proper permissions
  file:
    path: "{{ item }}"
    state: directory
    owner: "{{ php_fpm_pool_user }}"
    group: "{{ php_fpm_pool_group }}"
    mode: '0775'
    recurse: yes
  loop:
    - "{{ ansistrano_release_path.stdout }}/var/cache"
    - "{{ ansistrano_release_path.stdout }}/var/log"
```

**Contract Requirements**:
- MUST create `.env.local` with all required environment variables
- MUST use `no_log: true` when handling secrets
- MUST install Composer dependencies with optimization
- MUST run database migrations (abort deployment if fail)
- MUST warm up Symfony cache
- MUST set correct file permissions for web server

---

### After Symlink Hook (`hooks/after-symlink.yml`)

Executed AFTER the new release is symlinked as `current`.

```yaml
---
- name: Reload PHP-FPM (graceful reload)
  service:
    name: "{{ php_fpm_service }}"
    state: reloaded
  become: yes
  become_user: root

- name: Clear OPcache via CLI
  command: php bin/console cache:pool:clear cache.global_clearer --env={{ app_env }}
  args:
    chdir: "{{ ansistrano_deploy_to }}/current"
  ignore_errors: yes

- name: Restart Symfony Messenger consumers
  systemd:
    name: symfony-messenger-consume
    state: restarted
  become: yes
  become_user: root
  ignore_errors: yes  # Service might not exist

- name: Wait for application to be ready
  uri:
    url: "https://{{ app_domain }}/health"
    status_code: 200
  register: health_result
  retries: 5
  delay: 3
  until: health_result.status == 200

- name: Send deployment notification
  uri:
    url: "{{ lookup('env', 'SLACK_WEBHOOK_URL') }}"
    method: POST
    body_format: json
    body:
      text: "✅ Deployment to {{ env_name }} successful"
      attachments:
        - color: "good"
          fields:
            - title: "Environment"
              value: "{{ env_name }}"
              short: true
            - title: "Branch/Commit"
              value: "{{ ansistrano_git_branch }}"
              short: true
  when: lookup('env', 'SLACK_WEBHOOK_URL') | default('') != ''
  ignore_errors: yes
```

**Contract Requirements**:
- MUST reload PHP-FPM (not restart, to avoid downtime)
- MUST clear OPcache
- SHOULD restart Messenger consumers (if applicable)
- MUST verify application health check
- SHOULD send deployment notification

---

## Ansible Configuration Contract

### `ansible.cfg`

```ini
[defaults]
# Inventory
inventory = inventories/production/hosts
host_key_checking = False

# Output
stdout_callback = yaml
bin_ansible_callbacks = True

# SSH
remote_user = deploy
private_key_file = ~/.ssh/id_rsa_deploy

# Performance
forks = 10
gathering = smart
fact_caching = jsonfile
fact_caching_connection = /tmp/ansible_facts
fact_caching_timeout = 3600

# Privilege escalation
become = True
become_method = sudo
become_user = root
become_ask_pass = False

# Roles
roles_path = ./roles:~/.ansible/roles:/usr/share/ansible/roles

# Vault
vault_password_file = .vault_password  # For local use only (NOT committed)

[ssh_connection]
ssh_args = -o ControlMaster=auto -o ControlPersist=60s
pipelining = True
```

**Contract Requirements**:
- **NEVER commit** `.vault_password` file to Git
- **MUST use** Jenkins credential binding for vault password in CI
- **SSH key**: Deploy user SSH key must be in `~/.ssh/id_rsa_deploy`

---

## Role Requirements Contract

### `roles/requirements.yml`

```yaml
---
- src: ansistrano.deploy
  version: 3.10.0

- src: ansistrano.rollback
  version: 3.1.0
```

**Installation**:
```bash
ansible-galaxy install -r roles/requirements.yml
```

**Contract Requirements**:
- MUST pin specific versions of roles (no `latest`)
- MUST run installation before first deployment
- SHOULD update versions cautiously (test in test environment first)

---

## Jenkins Integration Contract

### Ansible Playbook Invocation from Jenkins

```groovy
// In Jenkinsfile
stage('Deploy to Test') {
    steps {
        ansiblePlaybook(
            playbook: 'deployment/deploy.yml',
            inventory: 'deployment/inventories/test/hosts',
            credentialsId: 'ansible-vault-password',
            extras: "-e 'branch=${GIT_COMMIT}'",
            colorized: true
        )
    }
}
```

**Contract Requirements**:
- MUST pass `branch` variable with Git commit SHA
- MUST use Jenkins credential binding for vault password
- SHOULD enable colorized output for better visibility

---

## Validation Checklist

Before committing inventory changes:

- [ ] All sensitive variables moved to `vault.yml` files
- [ ] Vault files encrypted with `ansible-vault encrypt`
- [ ] Vault password NOT committed to Git
- [ ] Host connection tested with `ansible all -m ping`
- [ ] Required variables defined in `group_vars/all.yml`
- [ ] Deploy playbook tested on test environment
- [ ] Rollback playbook tested on test environment
- [ ] SSH keys deployed to all target servers
- [ ] Deploy user has sudo permissions on target servers

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| `Permission denied (publickey)` | Add deploy SSH key to server: `ssh-copy-id deploy@server` |
| `sudo: no tty present` | Edit sudoers: `deploy ALL=(ALL) NOPASSWD:ALL` |
| `ERROR! Attempting to decrypt but no vault secrets found` | Provide vault password: `--vault-password-file .vault_password` |
| `Host key verification failed` | Set `host_key_checking = False` in `ansible.cfg` |

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-02-13 | Initial inventory contract definition |

---

## See Also

- [jenkinsfile-schema.md](jenkinsfile-schema.md) - Jenkins pipeline contract
- [deployment-api.yaml](deployment-api.yaml) - Health check API contract
- [../research.md](../research.md) - Ansistrano research and best practices
