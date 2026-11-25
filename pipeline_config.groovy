//aws_account = 'espana'
//Agents con version de PHP instalado
agent= 'op_jenkins_mx_dev_slave_2023_php'
init_agent = 'op_jenkins_mx_dev_slave_2023_php'

is_github_repo = 'true'
git_credentials_id = 'jenkins-github-wipop-bbva'
github_token_credential_id =  'jenkins-github-wipop-bbva'
jte {
  pipeline_template = "prestashop_module"
}

libraries {
  prestashop
}

application_environments {

  dev {
    ignore = false
    ecr_region = 'eu-west-1'
    ecr_registry = '727646475477.dkr.ecr.eu-west-1.amazonaws.com'
  }


  qa {
    ignore = true
    ecr_region = 'eu-west-1'
    ecr_registry = '767397937597.dkr.ecr.eu-west-1.amazonaws.com'
  }

  sandbox {
    ignore = true
    ecr_region = 'eu-west-1'
    ecr_registry = '022499044376.dkr.ecr.eu-west-1.amazonaws.com'
  }

  prod {
    ignore = true
    ecr_region = 'eu-west-1'
    ecr_registry = '216989119889.dkr.ecr.eu-west-1.amazonaws.com'
  }

}
release_active = false
