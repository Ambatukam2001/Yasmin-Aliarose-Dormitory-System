import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Yasmin & Aliarose Dormitory",
  description: "Dormitory booking and management system",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap"
          rel="stylesheet"
        />
        <link
          rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
          crossOrigin="anonymous"
          referrerPolicy="no-referrer"
        />
        <script
          src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
          crossOrigin="anonymous"
          referrerPolicy="no-referrer"
        ></script>
        <link rel="stylesheet" href="/assets/css/style.css" />
        <link rel="icon" type="image/png" href="/assets/images/logo.png" />
      </head>
      <body>{children}</body>
    </html>
  );
}
