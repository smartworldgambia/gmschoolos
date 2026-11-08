# Stub Terraform for Gambia SchoolOS AWS target architecture.
# NOT applied automatically. Fill account/region/secrets before any plan/apply.

terraform {
  required_version = ">= 1.5.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

variable "project" {
  type    = string
  default = "schoolos"
}

variable "environment" {
  type    = string
  default = "staging"
}

variable "app_min" {
  type    = number
  default = 1
}

variable "app_max" {
  type    = number
  default = 4
}

variable "db_connection_budget" {
  type    = number
  default = 80
}

locals {
  tags = {
    project = var.project
    env     = var.environment
    service = "app"
  }
}

# Placeholder resources — replace with real module sources when migrating.
output "notes" {
  value = "Provision ALB + ECS services (app + worker pools), RDS, ElastiCache, S3. Cap app_max by db_connection_budget. Do not claim 2M users from this stub."
}

output "tags" {
  value = local.tags
}

output "autoscaling_caps" {
  value = {
    app_min               = var.app_min
    app_max               = var.app_max
    db_connection_budget  = var.db_connection_budget
  }
}
