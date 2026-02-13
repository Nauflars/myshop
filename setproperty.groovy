import jenkins.model.Jenkins;
System.setProperty("hudson.plugins.git.GitSCM.ALLOW_LOCAL_CHECKOUT", "true");
Jenkins.instance.save();
println("ALLOW_LOCAL_CHECKOUT set to: " + System.getProperty("hudson.plugins.git.GitSCM.ALLOW_LOCAL_CHECKOUT"));
