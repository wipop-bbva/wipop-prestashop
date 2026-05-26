aws_account = 'espana'

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
