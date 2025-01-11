using Microsoft.EntityFrameworkCore;

namespace gameconNET.Models.DB;

public class GameConContext : DbContext
{
    public DbSet<Permission> Permissions { get; set; }
    public DbSet<Role> Roles { get; set; }
    public DbSet<User> Users { get; set; }

    protected override void OnConfiguring(DbContextOptionsBuilder optionsBuilder)
        => optionsBuilder
            .UseMySQL(@"Server=localhost;Port=13306;Database=gamecon;Uid=root;Pwd=root;SslMode=Preferred;");

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        modelBuilder.Entity<Permission>()
            .HasMany<Role>(p => p.Roles)
            .WithMany(r => r.Permissions)
            .UsingEntity(
                "prava_role",
                l => l.HasOne(typeof(Role)).WithMany().HasForeignKey("id_role"),
                r => r.HasOne(typeof(Permission)).WithMany().HasForeignKey("id_prava"));
        modelBuilder.Entity<User>()
            .HasMany<Role>(u => u.Roles)
            .WithMany()
            .UsingEntity("uzivatele_role",
                l => l.HasOne(typeof(Role)).WithMany().HasForeignKey("id_role"),
                r => r.HasOne(typeof(User)).WithMany().HasForeignKey("id_uzivatele"));
    }
};
