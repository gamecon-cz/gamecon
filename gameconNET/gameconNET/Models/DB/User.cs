using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace gameconNET.Models.DB;

[Table(("uzivatele_hodnoty"))]
public class User
{
    [Key]
    [Required]
    [Column("id_uzivatele")]
    public int Id { get; set; }

    [Required]
    [MaxLength(255)]
    [Column("email1_uzivatele")]
    public string Email { get; set; }

    public List<Role> Roles { get; } = new();
}
