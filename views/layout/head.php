<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CureRx Pro - Digital Prescription System</title>

  <!-- FontAwesome 6 Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Custom Stylesheets — filemtime query busts Electron's renderer cache on change -->
  <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/style.css'); ?>">
  <link rel="stylesheet" href="assets/css/print.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/print.css'); ?>">

  <style>
    body.role-receptionist [data-tab="tab-builder"],
    body.role-receptionist [data-tab="tab-templates"],
    body.role-receptionist [data-tab="tab-medicines"],
    body.role-receptionist [data-tab="tab-history"] {
      display: none !important;
    }
    body.role-doctor [data-tab="tab-channeling-billing"] {
      display: none !important;
    }
  </style>
</head>
