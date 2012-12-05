<?php
/**
 * @author Adam Dear
 *
 * This is a quick script to handle deploying git source code to one or more
 * Amazon EC2 instances that are behind an Elastic Load Balancer
 *
 * This is licensed under the MIT License.
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the “Software”), to deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions
 * of the Software.
 */
require 'sdk-1.5.15/sdk.class.php';

/** Fill in the values below to match your application */
$loadBalancer   = "ELB NAME HERE";
$docRoot        = "PUT PATH TO YOUR APPLICATION HERE";
$deploymentUser = "DEPLOYMENT USER HERE";

$elb      = new AmazonELB();
$response = $elb->describe_load_balancers(array('LoadBalancerNames' => $loadBalancer));

foreach ($response->body->LoadBalancerDescriptions() as $item) {
    $members = $item->member;
    foreach ($members->Instances->member as $instance) {
        echo "\n-------------------------------------------------\n";
        $instanceId = (string)$instance->InstanceId;

        /** Deregister this instance so that it doesn't get requests while the code is updating */
        $deregResponse = $elb->deregister_instances_from_load_balancer($loadBalancer, array(array('InstanceId' => $instanceId)));

        if ($deregResponse->isOk()) {
            $ec2  = new AmazonEC2();
            $desc = $ec2->describe_instances(array('InstanceId' => $instanceId));
            /** Grab the public DNS so we can ssh into the server */
            $dns = (string)$desc->body->reservationSet->item->instancesSet->item->dnsName;

            /** Deploy the code and install dependencies from Composer */
            $deployCommand = "ssh {$deploymentUser}@{$dns} 'cd {$docRoot}; git pull origin master; composer install'";
            passthru($deployCommand);

            /** Register instance with LB again so it can get requests again */
            $elb->register_instances_with_load_balancer($loadBalancer, array(array('InstanceId' => $instanceId)));
            do {
                /**
                 * Give the Registration a second to complete before we go on. We'll wait and then check every second
                 * until the instance is healthy in the LB.
                 */
                sleep(1);
                $healthStatus = $elb->describe_instance_health($loadBalancer, array('Instances' => array(array('InstanceId' => $instanceId))));
                $inService    = (string)$healthStatus->body->DescribeInstanceHealthResult->InstanceStates->member->State;
            } while ($inService != "InService");
        } else {
            echo "\n\nCouldn't deregister instance\n";
        }
    }
}
