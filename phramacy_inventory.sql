create database pharmacy_inventory;

use pharmacy_inventory;


create table users(
    userId int auto_increment primary key ,
    fullName varchar(100) not null,
    userName varchar(50) not null unique,
    email varchar(100) not null unique,
    phone varchar(20),
    password varchar(255) not null,
    role enum ('admin', 'pharmacist', 'staff'),
    status enum ('active', 'inactive') default 'active',
    createdAt timestamp default current_timestamp

);

create table categories(
    categoryId int auto_increment primary key,
    categoryName varchar(100) not null
);

create table suppliers(
    supplierId int auto_increment primary key,
    supplierName varchar(100) not null,
    contactName varchar(100),
    email varchar(100),
    phone varchar(20),
    address text
);

create table medicines(
    medicineId int auto_increment primary key,
    medicineName varchar(100) not null,
    barcode varchar(50) unique,
    categoryId int ,
    supplierId int,
    price decimal(10,2),
    sellingPrice decimal(10,2),
    quantity int,
    manufactureDate date,
    expiryDate date,
    minStock int,
    foreign key (categoryId) references categories(categoryId),
    foreign key (supplierId) references suppliers(supplierId)
);

create table sales(
    saleId int auto_increment primary key,
    userId int,
    totalAmount decimal(10,2),
    paymentMethod enum('cash', 'Mobile Money'),
    saleDate timestamp default current_timestamp,
    foreign key (userId) references users(userId)

);

create table sale_details(
    detailId int auto_increment primary key,
    saleId int,
    medicineId int,
    quantity int,
    price decimal(10,2),
    subtotal decimal(10,2),
    foreign key (saleId) references sales(saleId),
    foreign key (medicineId) references medicines(medicineId)
);

create table stock_movements(
    movementId int auto_increment primary key,
    medicineId int,
    quantity int,
    movementType enum('purchase', 'sale', 'adjustment'),
    movementDate timestamp default current_timestamp,
    foreign key (medicineId) references medicines(medicineId)
);

-- sample data users

insert into users (userId, fullName, userName, email, phone, password, role) values
(1, 'John Doe', 'johndoe', 'john@gmail.com', '+255 761111111', 'password123', 'admin'),
(2, 'Jane Smith', 'janesmith', 'jane@gmail.com', '+255 762222222', 'password456', 'pharmacist'),
(3, 'Michael Johnson', 'michaelj', 'michael@gmail.com', '+255 763333333', 'password789', 'staff'),
(4, 'Emily Davis', 'emilyd', 'emily@gmail.com', '+255 764444444', 'password321', 'pharmacist'),
(5, 'David Wilson', 'davidw', 'david@gmail.com', '+255 765555555', 'password654', 'admin');


-- sample data categories

insert into categories (categoryId, categoryName) values
(1, 'Analgesics'),
(2, 'Antibiotics'),
(3, 'Antihistamines'),
(4, 'Antacids'),
(5, 'Vitamins'),
(6, 'Antidiabetic'),
(7, 'Diabetes'),
(8, 'Cardiovascular'),
(9, 'Dermatology'),
(10, 'Gastrointestinal');


-- sample data suppliers

insert into suppliers (supplierId, supplierName, contactName, email, phone, address) values
(1, 'corpMed Pharmaceuticals', 'John Smith', 'john@corpmed.com', '+256 771221111', '123 Main Street, City, uganda'),
(2, 'HealthPlus Distributors', 'Jane Doe', 'info@healthplus.com', '+256 772332222', '456 Elm Avenue, City, uganda'),
(3, 'Global Meds Ltd', 'Michael Johnson', 'michael@globalmeds.com', '+256 773443333', '789 Oak Street, City, uganda');

-- sample data medicines

insert into medicines (medicineId, medicineName, barcode, categoryId, supplierId, price, sellingPrice, quantity, manufactureDate, expiryDate, minStock) values
(1, 'Paracetamol', null, 1, 1, 1500.00, 1950.00, 1000, '2023-01-01', '2025-01-01', 100),
(2, 'Amoxicillin', null, 2, 2, 2000.00, 2600.00, 500, '2023-02-01', '2025-02-01', 100),
(3, 'Ibuprofen', null, 1, 1, 1000.00, 1300.00, 800, '2023-03-01', '2025-03-01', 100),
(4, 'Metformin', null, 6, 3, 1000.00, 1300.00, 600, '2023-04-01', '2025-04-01', 100),
(5, 'Cetirizine', null, 3, 2, 4000.00, 5200.00, 700, '2023-04-01', '2025-06-01', 100);

-- sample data sales

insert into sales (saleId, userId, totalAmount, paymentMethod, saleDate) values
(1, 1, 5000, 'cash', '2024-06-26 10:00:00'),
(2, 2, 3000, 'Mobile Money', '2024-06-26 11:00:00'),
(3, 3, 7000, 'cash', '2024-06-26 12:00:00'),
(4, 4, 8000, 'Mobile Money', '2024-06-26 13:00:00'),
(5, 5, 6000, 'cash', '2024-06-26 14:00:00');


-- sample data sale_details

insert into sale_details (detailId, saleId, medicineId, quantity, price, subtotal) values
(1, 1, 1, 2, 1500, 3000),
(2, 1, 2, 1, 2000, 2000),
(3, 2, 3, 3, 1000, 3000),
(4, 3, 4, 5, 1000, 5000),
(5, 4, 5, 2, 4000, 8000),
(6, 5, 1, 4, 1500, 6000);
