using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace gameconNET.Models.DB;

[Table("r_prava_soupis")]
public class Permission
{
    [Key]
    [Required]
    [Column("id_prava")]
    public int Id { get; set; }

    [MaxLength(255)]
    [Required]
    [Column("jmeno_prava")]
    public string Name { get; set; }

    [Column("popis_prava")]
    public string? Description { get; set; }

    // many-to-many configured in GameConContext.OnModelCreating()
    public List<Role> Roles { get; } = [];
}
