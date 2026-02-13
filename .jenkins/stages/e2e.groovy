// E2E Test stage script for Jenkins pipeline
// Executes Playwright tests against deployed environment

def runE2ETests(String environment, String baseUrl) {
    stage("E2E Tests - ${environment}") {
        steps {
            script {
                echo "→ Running E2E tests against ${baseUrl}..."
                
                dir('tests/E2E') {
                    // Install Playwright and dependencies
                    sh 'npm ci --silent'
                    sh 'npx playwright install --with-deps chromium firefox webkit'
                    
                    // Run tests with retries
                    def testResult = sh(
                        script: "BASE_URL=${baseUrl} npm test -- --workers=4 --retries=2",
                        returnStatus: true
                    )
                    
                    // Archive test results
                    archiveArtifacts artifacts: 'playwright-report/**/*', allowEmptyArchive: true
                    archiveArtifacts artifacts: 'test-results/**/*', allowEmptyArchive: true
                    
                    // Publish HTML report
                    publishHTML([
                        allowMissing: false,
                        alwaysLinkToLastBuild: true,
                        keepAll: true,
                        reportDir: 'playwright-report',
                        reportFiles: 'index.html',
                        reportName: "Playwright Report - ${environment}",
                        reportTitles: "E2E Test Results"
                    ])
                    
                    // JUnit test results
                    junit allowEmptyResults: true, testResults: 'results.xml'
                    
                    // Fail build if tests failed
                    if (testResult != 0) {
                        error("E2E tests failed on ${environment}")
                    }
                }
                
                echo "✓ E2E tests passed on ${environment}"
            }
        }
    }
}

return this
