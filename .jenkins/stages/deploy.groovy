// Deploy stage reusable script for Jenkins pipeline
// This can be loaded as a shared library in Jenkinsfile

def deploy(String environment, String containerName) {
    stage("Deploy to ${environment}") {
        steps {
            script {
                echo "→ Deploying to ${environment} environment (${containerName})..."
                
                // Check container health
                sh """
                    docker ps -q -f name=${containerName} || {
                        echo "Error: Container ${containerName} is not running"
                        exit 1
                    }
                """
                
                // Check disk space
                def diskUsage = sh(
                    script: "df / | awk 'NR==2 {print \$5}' | sed 's/%//'",
                    returnStdout: true
                ).trim().toInteger()
                
                if (diskUsage > 80) {
                    error("Disk usage is ${diskUsage}% - deployment aborted")
                }
                
                // Deploy using Ansible
                sh """
                    ansible-playbook deployment/deploy-local.yml \\
                        -i deployment/inventories/local-${environment}/hosts \\
                        -e "branch=\${GIT_COMMIT}" \\
                        -e "container_name=${containerName}" \\
                        --vault-password-file=\${ANSIBLE_VAULT_PASSWORD_FILE}
                """
                
                echo "✓ Deployment to ${environment} completed"
            }
        }
    }
}

return this
