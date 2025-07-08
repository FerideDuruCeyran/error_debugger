using System;

namespace ArizaTakipSistemi.Models
{
    public class Fault
    {
        public int Id { get; set; }
        public string FaultType { get; set; }
        public string Title { get; set; }
        public string Content { get; set; }
        public string FilePath { get; set; }
        public string Department { get; set; }
        public DateTime Date { get; set; }
        public string Status { get; set; } // Bekliyor, Onaylandı, Tamamlandı
        public string TrackingNumber { get; set; }
        public int UserId { get; set; }
        public User User { get; set; }
    }
} 