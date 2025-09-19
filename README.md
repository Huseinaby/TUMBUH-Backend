# TUMBUH - Backend Service

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
</p>

This repository contains the backend service for **TUMBUH**,  a comprehensive digital ecosystem designed to connect and empower plant enthusiasts across Indonesia. More than just an app, TUMBUH integrates four core pillars: AI-Powered Education, an integrated Marketplace, a location-based Community Forum, and IoT Plant Monitoring. 

The platform features a dynamic learning module powered by Gemini AI, a marketplace with full integration for payment gateways (Midtrans) and shipping calculation (RajaOngkir), location-based community forums to foster interaction, and an advanced IoT system for automated plant care. This project aims to be the super-app for anyone passionate or professionally involved in the world of horticulture.

##  Key Features

-    **AI-Powered Education Platform**
     -  Dynamic Learning Modules, Generates reading materials, articles (via Google API), and relevant videos (via YouTube API) curated specifically from a user-inputted plant name.
     -  Structured Content, Educational materials are divided into four practical categories: "About the Plant," "Planting Guide," "Care Instructions," and "Business Ideas."
     -  Interactive Quizzes, Gemini AI automatically generates quiz questions based on the educational content to test user knowledge.

-   **Integrated Marketplace**
     -   Shopping Cart Functionality, A seamless e-commerce experience for buying and selling plant-related products.
     -   Payment Gateway Integration, Connected with Midtrans to facilitate a wide range of secure and reliable payment methods.
     -   Automated Shipping Calculation, Integrated with the RajaOngkir API for real-time shipping cost calculation.

-   **Location-Based Community Forum**
     -   Regional Groups, Forums are organized into groups based on city/regency to facilitate locally relevant discussions.
     -   Multimedia Posts, Users can create text posts and embed images to share experiences or ask questions.
     -   Commenting System, Enables in-depth interaction and discussion on each post.

-   **IoT Device Integration**
     -   Real-Time Monitoring with firebase, Monitors vital plant conditions such as ambient temperature, air humidity, and soil moisture.
     -   Automated Watering System, A water pump is automatically activated when sensors detect dry soil, ensuring plants are always hydrated.     

##  Tech Stack

- **Framework**: Laravel 11  
- **Language**: PHP 8.2  
- **Database**: MySQL 
- **Realtime & IoT**: Firebase (via Kreait)
- **Admin Panel**: Filament 3  
- **API**: RESTful API  
- **Authentication & Security**:  
  - Laravel Sanctum (API tokens)  
  - Laravel Socialite (OAuth login)  
- **Payment Gateway**: Midtrans  
- **Email & Notifications**: Mailgun (via Symfony Mailer)  
- **External Integrations**: 
  - Google API Client
  - YouTube Data API V3
- **Package Manager**: Composer  


<!-- ##  API Documentation

Here are some of the main available endpoints.

### Education

| Method | Endpoint         | Description                         |
| :----- | :--------------- | :---------------------------------- |
| `GET`  | `/api/modul`     | Register a new user.                |
| `GET`  | `/api/modul/{id}`| Log in to obtain a Bearer Token.    |
| `GET`  | `/api/`        | Log out and invalidate the token (Auth Required). |

### Plants (Authentication Required)

| Method | Endpoint        | Description                                  |
| :----- | :-------------- | :------------------------------------------- |
| `GET`  | `/plants`       | Get all plants for the authenticated user.   |
| `POST` | `/plants`       | Add a new plant to the collection.           |
| `GET`  | `/plants/{id}`  | Get the details of a specific plant.         |
| `PUT`  | `/plants/{id}`  | Update a plant's information.                |
| `DELETE`| `/plants/{id}`  | Delete a plant from the collection.          | -->